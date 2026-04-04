<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PayMongoWebhookEvent;
use App\Models\Cart;
use App\Models\Product;
use App\Models\VendorApplication;
use App\Models\VendorClosedDate;
use App\Models\ReservationAvailabilityCache;
use App\Services\VendorFinanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class CheckoutController extends Controller
{
    /**
     * Initialize checkout - now returns vendor delivery fee (no tax)
     */
    public function initializeCheckout(Request $request)
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'cart_item_ids' => 'required_without:product_id|array|min:1',
                'cart_item_ids.*' => 'required|integer|exists:carts,id',
                'product_id' => 'required_without:cart_item_ids|integer|exists:products,id',
                'quantity' => 'required_with:product_id|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            if (empty($user->address)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please complete your address in your profile before continuing.',
                    'requires_address' => true
                ], 400);
            }

            if ($request->filled('product_id')) {
                // Direct Checkout
                $product = Product::with(['primaryImage', 'images', 'owner', 'models'])->findOrFail($request->product_id);
                $cartItems = collect([
                    (object)[
                        'id' => null,
                        'product_id' => $product->id,
                        'product' => $product,
                        'quantity' => (int)$request->quantity,
                        'price' => $product->selling_price,
                        'is_available' => true,
                        'color' => $request->color,
                        'size' => $request->size,
                        'notes' => $request->notes,
                        'customizations' => $request->customizations,
                    ]
                ]);
            } else {
                // Cart Checkout
                $cartItems = Cart::whereIn('id', $request->cart_item_ids)
                    ->where('user_id', $user->id)
                    ->with(['product.primaryImage', 'product.images', 'product.owner', 'product.models'])
                    ->get();
            }

            if ($cartItems->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No items found for checkout'
                ], 404);
            }

            $vendorIds = $cartItems->pluck('product.owner_id')->unique();
            if ($vendorIds->count() > 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot checkout items from multiple vendors at once'
                ], 400);
            }

            $vendorId = $vendorIds->first();
            $vendor = \App\Models\User::find($vendorId);

            $vendorApplication = VendorApplication::where('email', $vendor->email)
                ->where('status', 'approved')
                ->first();

            // Calculate totals
            $subtotal = 0;
            $items = [];

            foreach ($cartItems as $cartItem) {
                if (!$cartItem->is_available) {
                    return response()->json([
                        'success' => false,
                        'message' => "Product '{$cartItem->product->product_name}' is no longer available"
                    ], 400);
                }

                $effectivePrice = $cartItem->product->discount_price 
                    ? (float) $cartItem->product->discount_price 
                    : (float) $cartItem->product->selling_price;

                $itemSubtotal = $effectivePrice * $cartItem->quantity;
                $subtotal += $itemSubtotal;

                // Get 3D model if exists
                $model3d = $cartItem->product->models->first();

                $effectivePrice = $cartItem->product->discount_price
    ? (float) $cartItem->product->discount_price
    : (float) $cartItem->product->selling_price;

                $items[] = [
                    'cart_item_id'   => $cartItem->id,
                    'product_id'     => $cartItem->product_id,
                    'product_name'   => $cartItem->product->product_name,
                    'product_image'  => $cartItem->product->primary_image->image_url
                                        ?? ($cartItem->product->images[0]->image_url ?? null),
                    'quantity'       => $cartItem->quantity,
                    'unit_price'     => $effectivePrice,           // ← correct
                    'original_price' => (float) $cartItem->product->selling_price,
                    'discount_price' => $cartItem->product->discount_price
                                        ? (float) $cartItem->product->discount_price
                                        : null,
                    'subtotal'       => $effectivePrice * $cartItem->quantity,
                    'color' => $cartItem->color,
                    'size' => $cartItem->size,
                    'notes' => $cartItem->notes,
                    'customizations' => $cartItem->customizations,
                    'model_3d_url' => $model3d?->model_url,
                    'model_3d_path' => $model3d?->model_path,
                    'preparation_days' => $this->resolveProductPreparationDays($cartItem->product),
                ];
            }

            $totalAmount = $subtotal;
            $preparationDays = $this->resolvePreparationDays($cartItems);

            // Payment methods
            $paymentMethods = $this->getAvailablePaymentMethods($vendorApplication);

            $checkoutData = [
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'contact_number' => $user->contact_number,
                    'address' => $user->address,
                ],
                'vendor' => [
                    'id' => $vendorId,
                    'store_name' => $vendorApplication->store_name,
                    'store_address' => $vendorApplication->store_address ?? '',
                    'contact_number' => $vendorApplication->contact_number ?? '',
                    'email' => $vendorApplication->email ?? '',
                    'max_orders_per_day' => $vendorApplication?->max_orders_per_day ?? 10,
                    'lead_time' => $vendorApplication?->lead_time ?? '',
                    'lead_time_days' => $vendorApplication?->reservationLeadTimeDays() ?? 3,
                    'preparation_days' => $preparationDays,
                    'same_day_delivery' => (bool) ($vendorApplication?->same_day_delivery ?? false),
                    'cutoff_times' => $vendorApplication?->cutoff_times ?? [],
                    'cutoff_time_today' => $vendorApplication?->cutoffTimeForDate(Carbon::now(VendorApplication::RESERVATION_TIMEZONE)),
                    'timezone' => VendorApplication::RESERVATION_TIMEZONE,
                ],
                'items' => $items,
                'delivery_type' => 'standard',
                'payment_methods' => [
                    'available_methods' => $paymentMethods,
                    'default_method' => !empty($paymentMethods) ? $paymentMethods[0]['type'] : 'cod',
                ],
                'summary' => [
                    'subtotal' => $subtotal,
                    'total_amount' => $totalAmount,
                    'preparation_days' => $preparationDays,
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $checkoutData,
                'message' => 'Checkout initialized successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Checkout initialization error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize checkout'
            ], 500);
        }
    }

    /**
     * Create order with reservation date using vendor-specific reservation rules.
     */
    public function createOrder(Request $request)
    {
        try {
            $user = Auth::user();

            $validator = Validator::make($request->all(), [
                'payment_method' => 'required|in:bank_transfer,gcash,maya,cod,card',
                'delivery_address' => 'required|string',
                'contact_number' => 'required|string',
                'delivery_notes' => 'nullable|string',
                'customer_notes' => 'nullable|string',
                'reservation_date' => 'required|date_format:Y-m-d',
                'cart_item_ids' => 'required_without:product_id|array|min:1',
                'product_id' => 'required_without:cart_item_ids|integer|exists:products,id',
                'quantity' => 'required_with:product_id|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $reservationDate = Carbon::parse(
                $request->reservation_date,
                VendorApplication::RESERVATION_TIMEZONE
            )->startOfDay();

            if ($request->filled('product_id')) {
                // Direct Order
                $product = Product::with(['owner', 'primaryImage', 'images', 'models'])->findOrFail($request->product_id);
                $cartItems = collect([
                    (object)[
                        'id' => null,
                        'product_id' => $product->id,
                        'product' => $product,
                        'quantity' => $request->quantity,
                        'price' => $product->selling_price,
                        'color' => $request->color,
                        'size' => $request->size,
                        'notes' => $request->notes,
                        'customizations' => $request->customizations,
                    ]
                ]);
            } else {
                // Cart Order
                $cartItems = Cart::with(['product.owner', 'product.primaryImage', 'product.images', 'product.models'])
                    ->where('user_id', $user->id)
                    ->whereIn('id', $request->cart_item_ids)
                    ->get();
            }

            if ($cartItems->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No items found for order'
                ], 400);
            }

            $vendorUserIds = $cartItems->pluck('product.owner_id')->unique();

            if ($vendorUserIds->count() > 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot checkout items from multiple vendors at once'
                ], 400);
            }

            $vendorId = $vendorUserIds->first();
            $vendorUser = \App\Models\User::find($vendorId);

            $vendor = VendorApplication::where('email', $vendorUser->email)
                ->where('status', 'approved')
                ->where('payment_details_completed', true)
                ->first();

            if (!$vendor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vendor is not ready to accept orders'
                ], 400);
            }

            $preparationDays = $this->resolvePreparationDays($cartItems);
            $settings = $this->applyPreparationLeadTime(
                VendorApplication::buildReservationSettings($vendorUser, $vendor),
                $preparationDays
            );
            $nowInPh = Carbon::now($settings['timezone']);
            $todayInPh = $nowInPh->copy()->startOfDay();
            $maxDate = $todayInPh->copy()->addMonths(3);

            Log::info('Reservation date validation', [
                'input' => $request->reservation_date,
                'parsed' => $reservationDate->toDateString(),
                'today_ph' => $todayInPh->toDateString(),
                'timezone' => $settings['timezone'],
                'lead_time_days' => $settings['lead_time_days'],
                'preparation_days' => $preparationDays,
                'same_day_delivery' => $settings['same_day_delivery'],
                'cutoff_time_today' => $settings['cutoff_time_today'],
            ]);

            $reservationValidation = $this->validateReservationDateSelection(
                $reservationDate,
                $settings,
                $todayInPh
            );

            if (!$reservationValidation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $reservationValidation['message'],
                    'errors' => [
                        'reservation_date' => [$reservationValidation['field_error']]
                    ]
                ], 422);
            }

            if ($reservationDate->greaterThan($maxDate)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reservations can only be made up to 3 months in advance',
                    'errors' => [
                        'reservation_date' => ['Reservations cannot exceed 3 months in advance']
                    ]
                ], 422);
            }

            // Check if reservation date is closed
            if (VendorClosedDate::isDateClosed($vendorId, $reservationDate->toDateString())) {
                return response()->json([
                    'success' => false,
                    'message' => 'This date is not available for reservations'
                ], 400);
            }

            // Check max orders per day
            $maxOrders = $settings['max_orders_per_day'];
            $ordersCount = Order::where('vendor_id', $vendorId)
                ->whereDate('reservation_date', $reservationDate)
                ->whereNotIn('status', ['cancelled', 'failed'])
                ->count();

            if ($ordersCount >= $maxOrders) {
                return response()->json([
                    'success' => false,
                    'message' => 'This date is fully booked. Please select another date.'
                ], 400);
            }

            DB::beginTransaction();

            try {
                // Calculate totals (NO TAX)
                $subtotal = 0;
                $itemsData = [];
                
                foreach ($cartItems as $item) {
                    $price = $item->product->discount_price
                        ? (float) $item->product->discount_price
                        : (float) ($item->product->selling_price ?: $item->price);

                    if (!$price) {
                        throw new \Exception("Price not found for product: " . $item->product->product_name);
                    }

                    $itemSubtotal = $price * $item->quantity;
                    $subtotal += $itemSubtotal;
                    
                    // Get 3D model
                    $model3d = $item->product->models->first();
                    
                    $itemsData[] = [
                        'product_id'          => $item->product_id,
                        'product_name'        => $item->product->product_name ?? 'Unknown Product',
                        'product_description' => $item->product->product_description ?? '',
                        'product_image'       => $item->product->primary_image->image_url ??
                                                ($item->product->images[0]->image_url ?? ''),
                        'quantity'            => $item->quantity,
                        'unit_price'          => $price,
                        'subtotal'            => $itemSubtotal,
                        'color' => $item->color ?? '',
                        'size' => $item->size ?? '',
                        'notes' => $item->notes ?? '',
                        'customizations' => $item->customizations ?? [],
                        'model_3d_url' => $model3d?->model_url,
                        'model_3d_path' => $model3d?->model_path,
                        'preparation_time' => $this->resolveProductPreparationDays($item->product),
                    ];
                }

                $total = $subtotal;
                $paymentMethod = $request->payment_method;

                // Create order with CORRECT reservation date (Y-m-d format)
                $order = Order::create([
                    'user_id' => $user->id,
                    'vendor_id' => $vendorId,
                    'order_number' => 'ORD-' . strtoupper(uniqid()),
                    'subtotal' => $subtotal,
                    'delivery_fee' => 0,
                    'total_amount' => $total,
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'unpaid',
                    'delivery_address' => $request->delivery_address,
                    'delivery_contact_name' => $user->name,
                    'delivery_contact_number' => $request->contact_number,
                    'delivery_notes' => $request->delivery_notes ?? '',
                    'customer_notes' => $request->customer_notes ?? '',
                    'store_name' => $vendor->store_name,
                    'store_address' => $vendor->store_address ?? '',
                    'status' => 'pending',
                    'reservation_date' => $reservationDate->toDateString(), // Store as Y-m-d
                ]);

                Log::info('Order created with reservation date', [
                    'order_id' => $order->id,
                    'reservation_date' => $order->reservation_date,
                    'input_date' => $request->reservation_date,
                ]);

                // Create order items with 3D model references
                foreach ($itemsData as $itemData) {
                    $order->items()->create($itemData);
                }

                // Update reservation cache
                ReservationAvailabilityCache::updateForDate($vendorId, $reservationDate->toDateString());

                if ($request->filled('cart_item_ids')) {
                    Cart::where('user_id', $user->id)
                        ->whereIn('id', $request->cart_item_ids)
                        ->delete();
                }

                DB::commit();

                // Handle payment
                if (in_array($paymentMethod, ['gcash', 'maya', 'card', 'bank_transfer'], true)) {
                    if ($paymentMethod === 'bank_transfer' && ! $this->hasPayMongoConfiguration()) {
                        Log::warning('Falling back to manual bank transfer because PayMongo is not configured', [
                            'order_id' => $order->id,
                            'vendor_id' => $vendorId,
                        ]);

                        return response()->json([
                            'success' => true,
                            'message' => 'Order created. Awaiting bank transfer.',
                            'data' => [
                                'order' => [
                                    'id' => $order->id,
                                    'order_number' => $order->order_number,
                                    'total_amount' => $order->total_amount,
                                    'payment_method' => $order->payment_method,
                                    'status' => $order->status,
                                    'reservation_date' => $order->reservation_date,
                                ],
                            ],
                        ]);
                    }

                    $paymentResponse = $this->createPayMongoPayment($order, $paymentMethod);
                    
                    if ($paymentResponse['success']) {
                        $order->update([
                            'paymongo_payment_intent_id' => $paymentResponse['checkout_session_id'] ?? null,
                            'paymongo_source_id' => $paymentResponse['source_id'] ?? null,
                            'paymongo_checkout_url' => $paymentResponse['checkout_url'] ?? null,
                            'paymongo_response' => [
                                'checkout_session_id' => $paymentResponse['checkout_session_id'] ?? null,
                                'reference_number' => $paymentResponse['reference_number'] ?? null,
                                'payment_method' => $paymentMethod,
                            ],
                        ]);
                        
                        return response()->json([
                            'checkout_url' => $paymentResponse['checkout_url'],
                        ]);
                    }

                    $order->update([
                        'status' => 'failed',
                        'payment_status' => 'failed',
                        'paymongo_response' => [
                            'error' => $paymentResponse['message'] ?? 'Failed to initialize online payment.',
                            'payment_method' => $paymentMethod,
                        ],
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $paymentResponse['message'] ?? 'Failed to initialize online payment.',
                        'data' => [
                            'order' => [
                                'id' => $order->id,
                                'order_number' => $order->order_number,
                            ],
                        ],
                    ], 502);
                }

                // For COD or bank transfer
                return response()->json([
                    'success' => true,
                    'message' => 'Order created successfully',
                    'data' => [
                        'order' => [
                            'id' => $order->id,
                            'order_number' => $order->order_number,
                            'total_amount' => $order->total_amount,
                            'payment_method' => $order->payment_method,
                            'status' => $order->status,
                            'reservation_date' => $order->reservation_date,
                        ]
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Error creating order', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available payment methods
     */
    protected function getAvailablePaymentMethods(?VendorApplication $vendorApplication): array
    {
        if (!$vendorApplication) {
            return [];
        }

        $methods = [];

        if ($vendorApplication->payout_method === 'bank') {
            $methods[] = [
                'type' => 'bank_transfer',
                'name' => 'Bank Transfer',
                'description' => 'Direct bank transfer',
                'icon' => '🏦',
                'enabled' => true,
            ];
        }

        if ($vendorApplication->payout_method === 'gcash') {
            $methods[] = [
                'type' => 'gcash',
                'name' => 'GCash',
                'description' => 'Pay via GCash',
                'icon' => '💙',
                'enabled' => true,
            ];
        }

        if ($vendorApplication->payout_method === 'maya') {
            $methods[] = [
                'type' => 'maya',
                'name' => 'Maya',
                'description' => 'Pay via Maya',
                'icon' => '💚',
                'enabled' => true,
            ];
        }

        $methods[] = [
            'type' => 'cod',
            'name' => 'Cash on Delivery',
            'description' => 'Pay when you receive your order',
            'icon' => '💵',
            'enabled' => true,
        ];

        return $methods;
    }

    /**
     * Create PayMongo payment
     */
    private function createPayMongoPayment(Order $order, string $paymentMethod)
    {
        $secretKey = $this->getPayMongoSecretKey();

        if (!$secretKey) {
            Log::error('PayMongo secret key not configured', [
                'payment_method' => $paymentMethod,
                'order_id' => $order->id,
            ]);
            return ['success' => false, 'message' => 'Payment gateway not configured'];
        }

        $checkoutMethod = match ($paymentMethod) {
            'gcash' => 'gcash',
            'maya' => 'paymaya',
            'card' => 'card',
            'bank_transfer' => 'dob',
            default => null,
        };

        if (!$checkoutMethod) {
            return ['success' => false, 'message' => 'Unsupported online payment method.'];
        }

        $frontendUrl = rtrim(config('app.frontend_url', 'http://localhost:5173'), '/');
        $referenceNumber = $this->buildPayMongoReference($order);

        $order->loadMissing('user');

        try {
            $checkoutData = [
                'data' => [
                    'attributes' => [
                        'send_email_receipt' => true,
                        'show_description' => true,
                        'show_line_items' => true,
                        'customer' => [
                            'name' => $order->delivery_contact_name,
                            'email' => $order->user?->email,
                            'phone' => $order->delivery_contact_number ?: null,
                        ],
                        'description' => 'Order #' . $order->order_number .
                            ' | Reservation: ' . $order->reservation_date,
                        'line_items' => [
                            [
                                'currency' => 'PHP',
                                'amount' => (int) round($order->total_amount * 100),
                                'description' => 'Order from ' . $order->store_name,
                                'quantity' => 1,
                                'name' => 'Order #' . $order->order_number,
                            ]
                        ],
                        'payment_method_types' => [$checkoutMethod],
                        'success_url' => $frontendUrl . '/customer/orders?payment=success&reference=' . urlencode($referenceNumber),
                        'cancel_url' => $frontendUrl . '/customer/checkout?payment=cancelled',
                        'reference_number' => $referenceNumber,
                        'metadata' => [
                            'order_id' => (string) $order->id,
                            'order_number' => $order->order_number,
                            'reference_number' => $referenceNumber,
                            'reservation_date' => $order->reservation_date,
                            'payment_method' => $paymentMethod,
                        ],
                    ],
                ],
            ];

            Log::info('Creating PayMongo checkout session', [
                'order_id' => $order->id,
                'payment_method' => $paymentMethod,
                'checkout_method' => $checkoutMethod,
                'amount_centavos' => $checkoutData['data']['attributes']['line_items'][0]['amount'],
                'success_url' => $checkoutData['data']['attributes']['success_url'],
                'cancel_url' => $checkoutData['data']['attributes']['cancel_url'],
            ]);

            $response = Http::withBasicAuth($secretKey, '')
                ->acceptJson()
                ->timeout(30)
                ->post('https://api.paymongo.com/v1/checkout_sessions', $checkoutData);

            $responseData = $response->json();

            Log::info('PayMongo response', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'response' => $responseData,
            ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'message' => $responseData['errors'][0]['detail']
                        ?? $responseData['message']
                        ?? 'Payment gateway error. Please try again.',
                ];
            }

            if (!isset($responseData['data']['attributes']['checkout_url'])) {
                Log::error('PayMongo checkout URL missing', [
                    'order_id' => $order->id,
                    'response' => $responseData,
                ]);
                return ['success' => false, 'message' => 'Invalid PayMongo response'];
            }

            return [
                'success' => true,
                'checkout_session_id' => $responseData['data']['id'],
                'reference_number' => $referenceNumber,
                'checkout_url' => $responseData['data']['attributes']['checkout_url'],
            ];
        } catch (\Throwable $e) {
            Log::error('PayMongo API error', [
                'order_id' => $order->id,
                'payment_method' => $paymentMethod,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Payment gateway error. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }
    
    public function getOrder(Request $request, $orderId)
    {
        try {
            $user = Auth::user();

            $order = Order::with([
                'items', 
                'items.product:id,product_name,product_description', // Add this line
                'vendor:id,name,email',
            ])
                ->where('id', $orderId)
                ->where('user_id', $user->id)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $order
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching order', [
                'error' => $e->getMessage(),
                'order_id' => $orderId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch order'
            ], 500);
        }
    }

    /**
     * Handle PayMongo webhooks
     */
    public function handleWebhook(Request $request)
    {
        $webhookEvent = null;

        try {
            $payload = $request->all();
            Log::info('PayMongo Webhook Received:', $payload);

            if (app()->environment('local') && $request->has('test_mode')) {
                Log::info('Local test webhook processing');
                return $this->handleTestWebhook($request);
            }

            // Signature verification is already handled by VerifyPayMongoWebhook middleware
            // but we kept this as a fallback for non-middleware routes if any.
            // However, the logic here was incorrect for PayMongo's header format.
            // Since it's redundant, and likely wrong, we'll rely on the middleware.

            $eventType = $payload['data']['attributes']['type'] ?? null;
            $resource = $payload['data']['attributes']['data'] ?? null;
            $eventId = $payload['data']['id'] ?? null;

            if ($eventId && $eventType) {
                $webhookEvent = PayMongoWebhookEvent::firstOrCreate(
                    ['event_id' => $eventId],
                    [
                        'event_type' => $eventType,
                        'payload' => $payload,
                        'status' => 'pending',
                    ]
                );

                if ($webhookEvent->isProcessed()) {
                    return response()->json(['status' => 'already processed']);
                }

                if ($webhookEvent->event_type !== $eventType || $webhookEvent->payload !== $payload) {
                    $webhookEvent->update([
                        'event_type' => $eventType,
                        'payload' => $payload,
                    ]);
                }
            }

            Log::info('Processing webhook event:', [
                'event_type' => $eventType,
                'resource_type' => $resource['type'] ?? 'unknown'
            ]);

            switch ($eventType) {
                case 'checkout_session.payment.paid':
                    return $this->handleCheckoutSessionPaid($payload, $webhookEvent);
                    
                case 'checkout_session.payment.failed':
                    return $this->handleCheckoutSessionFailed($payload, $webhookEvent);
                    
                case 'payment.paid':
                    return $this->handlePaymentPaid($resource, $webhookEvent);
                    
                case 'payment.failed':
                    return $this->handlePaymentFailed($resource, $webhookEvent);
                    
                case 'source.chargeable':
                    return $this->handleSourceChargeable($resource, $webhookEvent);
                    
                default:
                    Log::warning('Unhandled webhook event type:', ['event_type' => $eventType]);
                    $webhookEvent?->markAsProcessed();
                    return response()->json(['success' => true, 'message' => 'Event ignored']);
            }

        } catch (\Exception $e) {
            $webhookEvent?->markAsFailed($e->getMessage());
            Log::error('Webhook processing error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    private function handleCheckoutSessionPaid(array $payload, ?PayMongoWebhookEvent $webhookEvent = null)
    {
        try {
            $eventAttributes = $payload['data']['attributes'] ?? [];
            $sessionData     = $eventAttributes['data'] ?? [];
            $sessionAttributes = $sessionData['attributes'] ?? [];
            $metadata        = $sessionAttributes['metadata'] ?? [];
            $order = $this->resolveWebhookOrder($sessionAttributes, $metadata);

            if (!$order) {
                Log::error('Checkout session paid but order not found', ['payload' => $payload]);
                return response()->json(['success' => false, 'message' => 'Order not found'], 404);
            }

            if ($order->payment_status === 'paid') {
                $webhookEvent?->update(['order_id' => $order->id]);
                $webhookEvent?->markAsProcessed();
                return response()->json(['success' => true, 'status' => 'already processed']);
            }

            $this->markOrderPaidFromWebhook($order, [
                'checkout_session_id' => $sessionData['id'] ?? null,
                'reference_number' => $sessionAttributes['reference_number'] ?? ($metadata['reference_number'] ?? null),
                'resource' => 'checkout_session.payment.paid',
            ]);

            // ── Credit vendor balance immediately on payment ──────────────────

            $webhookEvent?->update(['order_id' => $order->id]);
            $webhookEvent?->markAsProcessed();

            return response()->json(['success' => true, 'status' => 'processed']);

        } catch (\Exception $e) {
            $webhookEvent?->markAsFailed($e->getMessage());
            Log::error('handleCheckoutSessionPaid FAILED', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['success' => false, 'message' => 'Webhook processing failed'], 500);
        }
    }

    private function handlePaymentPaid(array $paymentData, ?PayMongoWebhookEvent $webhookEvent = null)
    {
        $attributes = $paymentData['attributes'] ?? [];
        $metadata = $attributes['metadata'] ?? [];
        $order = $this->resolveWebhookOrder($attributes, $metadata);

        if (!$order) {
            Log::error('Order not found for payment.paid webhook', ['payload' => $paymentData]);
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        $this->markOrderPaidFromWebhook($order, [
            'payment_id' => $paymentData['id'] ?? null,
            'reference_number' => $attributes['reference_number']
                ?? $attributes['external_reference_number']
                ?? ($metadata['reference_number'] ?? $metadata['pm_reference_number'] ?? null),
            'resource' => 'payment.paid',
        ]);

        $webhookEvent?->update(['order_id' => $order->id]);
        $webhookEvent?->markAsProcessed();

        Log::info('Order marked as paid via payment.paid webhook', ['order_id' => $order->id]);
        return response()->json(['success' => true, 'status' => 'processed']);
    }

    private function validateReservationAvailability(Order $order): bool
    {
        if (!$order->reservation_date) {
            return true;
        }

        $reservationDate = Carbon::parse(
            $order->reservation_date,
            VendorApplication::RESERVATION_TIMEZONE
        )->startOfDay();
        $today = Carbon::now(VendorApplication::RESERVATION_TIMEZONE)->startOfDay();
        $maxDate = $today->copy()->addMonths(3);

        if ($reservationDate->greaterThan($maxDate)) {
            return false;
        }

        if (VendorClosedDate::isDateClosed($order->vendor_id, $order->reservation_date)) {
            return false;
        }

        $vendor = \App\Models\User::find($order->vendor_id);
        $vendorApplication = VendorApplication::findApprovedForVendorUser($vendor);
        $settings = VendorApplication::buildReservationSettings($vendor, $vendorApplication, $today);

        if (!$this->validateReservationDateSelection($reservationDate, $settings, $today)['valid']) {
            return false;
        }

        $maxOrders = $settings['max_orders_per_day'];
        
        $ordersCount = Order::where('vendor_id', $order->vendor_id)
            ->whereDate('reservation_date', $reservationDate)
            ->where('id', '!=', $order->id)
            ->whereNotIn('status', ['cancelled', 'failed'])
            ->where('payment_status', 'paid')
            ->count();

        return $ordersCount < $maxOrders;
    }

    private function handlePaymentFailed($payload, ?PayMongoWebhookEvent $webhookEvent = null)
    {
        $paymentData = $payload['data']['attributes']['data'] ?? $payload;
        $attributes = $paymentData['attributes'] ?? [];
        $metadata = $attributes['metadata'] ?? [];
        $order = $this->resolveWebhookOrder($attributes, $metadata);

        if ($order) {
            $order->update([
                'payment_status' => 'failed',
                'status' => 'payment_failed',
            ]);
            $webhookEvent?->update(['order_id' => $order->id]);
            Log::info('Order marked as failed via webhook', ['order_id' => $order->id]);
        }

        $webhookEvent?->markAsProcessed();
        return response()->json(['success' => true, 'status' => 'processed']);
    }

    private function handleCheckoutSessionFailed($payload, ?PayMongoWebhookEvent $webhookEvent = null)
    {
        return $this->handlePaymentFailed($payload, $webhookEvent);
    }

    private function validateReservationDateSelection(Carbon $reservationDate, array $settings, Carbon $today): array
    {
        if ($reservationDate->lessThan($today)) {
            return [
                'valid' => false,
                'message' => 'Cannot book dates in the past',
                'field_error' => 'Please select today or a future date',
            ];
        }

        if ($reservationDate->isSameDay($today)) {
            if (!$settings['same_day_delivery']) {
                return [
                    'valid' => false,
                    'message' => 'Same-day delivery is not available for this vendor',
                    'field_error' => 'This vendor does not accept same-day delivery',
                ];
            }

            if ($settings['same_day_cutoff_reached']) {
                $cutoff = $settings['cutoff_time_today']
                    ? Carbon::createFromFormat(
                        'H:i',
                        $settings['cutoff_time_today'],
                        $settings['timezone']
                    )->format('g:i A')
                    : null;

                return [
                    'valid' => false,
                    'message' => $cutoff
                        ? "Same-day delivery cutoff has been reached for today. Cutoff time is {$cutoff} PH time."
                        : 'Same-day delivery cutoff has been reached for today.',
                    'field_error' => $cutoff
                        ? "Same-day orders are only available until {$cutoff} PH time"
                        : 'Same-day delivery is no longer available today',
                ];
            }

            return ['valid' => true, 'message' => null, 'field_error' => null];
        }

        $minimumDate = $today->copy()->addDays($settings['lead_time_days']);
        if ($reservationDate->lessThan($minimumDate)) {
            $days = $settings['lead_time_days'];

            return [
                'valid' => false,
                'message' => "Reservation must be at least {$days} day(s) from today to allow preparation time",
                'field_error' => "Please select a date at least {$days} day(s) from today",
            ];
        }

        return ['valid' => true, 'message' => null, 'field_error' => null];
    }
    

    private function handleSourceChargeable($payload, ?PayMongoWebhookEvent $webhookEvent = null)
    {
        $sourceData = $payload['data']['attributes']['data'] ?? $payload;
        $sourceId = $sourceData['id'] ?? null;
        $attributes = $sourceData['attributes'] ?? [];
        $metadata = $attributes['metadata'] ?? [];
        $order = $this->resolveWebhookOrder($attributes, $metadata);
        
        if (!$order || !$sourceId) {
            Log::error('Missing order or source_id in source.chargeable', $sourceData);
            return response()->json(['success' => false, 'message' => 'Missing order or source'], 400);
        }
        
        $order->update([
            'paymongo_source_id' => $sourceId,
        ]);

        $webhookEvent?->update(['order_id' => $order->id]);
        $webhookEvent?->markAsProcessed();
        
        Log::info('Source marked as chargeable', ['order_id' => $order->id, 'source_id' => $sourceId]);
        return response()->json(['success' => true, 'status' => 'processed']);
    }

    private function verifyWebhookSignature(Request $request): bool
    {
        if (app()->environment(['local', 'testing'])) {
            return true;
        }

        $secretKey = env('PAYMONGO_WEBHOOK_SECRET');
        $signature = $request->header('Paymongo-Signature');
        
        if (!$secretKey || !$signature) {
            Log::warning('Missing webhook secret or signature');
            return false;
        }

        $payload = $request->getContent();
        $parts = [];

        foreach (explode(',', $signature) as $part) {
            [$key, $value] = explode('=', $part, 2);
            $parts[$key] = $value;
        }

        $timestamp = $parts['t'] ?? '';
        $expectedSignature = $parts['te'] ?? '';
        $computedSignature = hash_hmac('sha256', $timestamp . '.' . $payload, $secretKey);
        
        return $expectedSignature !== '' && hash_equals($expectedSignature, $computedSignature);
    }

    /**
     * Handle test webhooks for local development
     */
    private function handleTestWebhook(Request $request)
    {
        $payload = $request->all();
        $type = $payload['type'] ?? $payload['data']['attributes']['type'] ?? 'unknown';
        
        Log::info('Handling test webhook', ['type' => $type]);
        
        if ($type === 'checkout_session.payment.paid') {
            return $this->handleCheckoutSessionPaid($payload);
        }
        
        return response()->json(['success' => true, 'message' => 'Test webhook received']);
    }

    public function paymentCallback(Request $request)
    {
        $success = $request->get('success') === 'true';
        $orderId = $request->get('order_id');

        if (!$orderId) {
            return redirect('/')->with('error', 'Invalid payment callback');
        }

        $order = Order::find($orderId);

        if (!$order) {
            return redirect('/')->with('error', 'Order not found');
        }

        if ($success) {
            return redirect('/customer/orders?payment=success&reference=' . urlencode($this->buildPayMongoReference($order)));
        } else {
            return redirect('/customer/checkout')->with('error', 'Payment failed. Please try again.');
        }
    }

    private function buildPayMongoReference(Order $order): string
    {
        return $order->order_number;
    }

    private function getPayMongoSecretKey(): ?string
    {
        return config('services.paymongo.secret_key') ?: env('PAYMONGO_SECRET_KEY');
    }

    private function hasPayMongoConfiguration(): bool
    {
        return !empty($this->getPayMongoSecretKey());
    }

    private function resolvePreparationDays(iterable $cartItems): int
    {
        $days = 0;

        foreach ($cartItems as $cartItem) {
            $days = max($days, $this->resolveProductPreparationDays($cartItem->product ?? null));
        }

        return $days;
    }

    private function resolveProductPreparationDays(?Product $product): int
    {
        if (!$product) {
            return 0;
        }

        $rawDays = $product->preparation_time
            ?? $product->preparation_days
            ?? $product->supplier_lead_time
            ?? 0;

        return max(0, (int) ceil((float) $rawDays));
    }

    private function applyPreparationLeadTime(array $settings, int $preparationDays): array
    {
        $effectiveLeadTime = max((int) ($settings['lead_time_days'] ?? 0), $preparationDays);
        $sameDayAvailable = ($settings['same_day_delivery'] ?? false)
            && $effectiveLeadTime === 0
            && !($settings['same_day_cutoff_reached'] ?? true);

        $settings['lead_time_days'] = $effectiveLeadTime;
        $settings['preparation_days'] = $preparationDays;
        $settings['same_day_available_today'] = $sameDayAvailable;

        return $settings;
    }

    private function resolveWebhookOrder(array $attributes = [], array $metadata = []): ?Order
    {
        $referenceNumber = $attributes['reference_number']
            ?? $attributes['external_reference_number']
            ?? $metadata['reference_number']
            ?? $metadata['pm_reference_number']
            ?? $metadata['order_number']
            ?? null;

        if ($referenceNumber) {
            $order = Order::where('order_number', $referenceNumber)->first();
            if ($order) {
                return $order;
            }
        }

        $orderId = $metadata['order_id'] ?? null;

        return $orderId ? Order::find($orderId) : null;
    }

    private function markOrderPaidFromWebhook(Order $order, array $paymentContext = []): void
    {
        DB::transaction(function () use ($order, $paymentContext) {
            $updates = [
                'payment_status' => 'paid',
                'paid_at' => $order->paid_at ?? now(),
            ];

            if (in_array($order->status, ['pending', 'failed', 'payment_failed'], true)) {
                $updates['status'] = 'processing';
            }

            $paymongoResponse = is_array($order->paymongo_response) ? $order->paymongo_response : [];

            $order->update(array_merge($updates, [
                'paymongo_payment_intent_id' => $paymentContext['checkout_session_id']
                    ?? $paymentContext['payment_id']
                    ?? $order->paymongo_payment_intent_id,
                'paymongo_response' => array_filter(array_merge($paymongoResponse, [
                    'reference_number' => $paymentContext['reference_number'] ?? null,
                    'webhook_resource' => $paymentContext['resource'] ?? null,
                    'paid_at' => now()->toIso8601String(),
                ]), fn ($value) => $value !== null),
            ]));

            if ($order->reservation_date) {
                ReservationAvailabilityCache::updateForDate(
                    $order->vendor_id,
                    $order->reservation_date
                );
            }
        });

        app(VendorFinanceService::class)->handleOrderPayment($order->fresh());
    }
}
