<?php
namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Cart;
use App\Models\Product;
use App\Models\VendorApplication;
use App\Models\VendorClosedDate;
use App\Models\ReservationAvailabilityCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Client;
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
                ];
            }

            // Vendor-defined delivery fee (NO TAX)
            $deliveryFee = $vendorApplication->default_delivery_fee ?? 50.00;
            $totalAmount = $subtotal + $deliveryFee;

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
                'delivery_fee' => 'required|numeric|min:0',
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

            $settings = VendorApplication::buildReservationSettings($vendorUser, $vendor);
            $nowInPh = Carbon::now($settings['timezone']);
            $todayInPh = $nowInPh->copy()->startOfDay();
            $maxDate = $todayInPh->copy()->addMonths(3);

            Log::info('Reservation date validation', [
                'input' => $request->reservation_date,
                'parsed' => $reservationDate->toDateString(),
                'today_ph' => $todayInPh->toDateString(),
                'timezone' => $settings['timezone'],
                'lead_time_days' => $settings['lead_time_days'],
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
                    ];
                }

                $deliveryFee = $request->delivery_fee;
                $total = $subtotal + $deliveryFee; // NO TAX
                $paymentMethod = $request->payment_method;

                // Create order with CORRECT reservation date (Y-m-d format)
                $order = Order::create([
                    'user_id' => $user->id,
                    'vendor_id' => $vendorId,
                    'order_number' => 'ORD-' . strtoupper(uniqid()),
                    'subtotal' => $subtotal,
                    'delivery_fee' => $deliveryFee,
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
                if (in_array($paymentMethod, ['gcash', 'maya', 'card'])) {
                    $paymentResponse = $this->createPayMongoPayment($order, $paymentMethod);
                    
                    if ($paymentResponse['success']) {
                        $order->update([
                            'paymongo_payment_intent_id' => $paymentResponse['payment_intent_id'] ?? null,
                            'paymongo_source_id' => $paymentResponse['source_id'] ?? null,
                            'paymongo_checkout_url' => $paymentResponse['checkout_url'] ?? null,
                        ]);
                        
                        return response()->json([
                            'success' => true,
                            'message' => 'Order created. Redirecting to payment...',
                            'data' => [
                                'order' => [
                                    'id' => $order->id,
                                    'order_number' => $order->order_number,
                                    'total_amount' => $order->total_amount,
                                    'payment_method' => $order->payment_method,
                                    'status' => $order->status,
                                    'reservation_date' => $order->reservation_date,
                                ],
                                'payment' => [
                                    'requires_redirect' => true,
                                    'redirect_url' => $paymentResponse['checkout_url'],
                                    'payment_intent_id' => $paymentResponse['payment_intent_id'],
                                    'message' => 'Redirecting to PayMongo checkout...'
                                ]
                            ]
                        ]);
                    }
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
        $secretKey = env('PAYMONGO_SECRET_KEY');

        if (!$secretKey) {
            Log::error('PayMongo secret key not configured');
            return ['success' => false, 'message' => 'Payment gateway not configured'];
        }

        $paymentTypes = [
            'gcash' => 'gcash',
            'maya' => 'paymaya',
            'card' => 'card',
        ];

        $sourceType = $paymentTypes[$paymentMethod] ?? 'gcash';

        $client = new Client(['timeout' => 30]);
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');

        try {
            $checkoutData = [
                'data' => [
                    'attributes' => [
                        'send_email_receipt' => true,
                        'show_description' => true,
                        'show_line_items' => true,

                        // ✅ CUSTOMER CONTAINER (THIS IS WHAT YOU WANT)
                        'customer' => [
                            'name' => $order->delivery_contact_name, // REQUIRED
                            'email' => $order->user->email,           // REQUIRED
                            'phone' => $order->delivery_contact_number ?: null, // OPTIONAL
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

                        'payment_method_types' => [$sourceType],

                        'success_url' => $frontendUrl . '/customer/orders',
                        'failure_url' => $frontendUrl . '/customer/checkout?payment=failed',

                        'metadata' => [
                            'order_id' => (string) $order->id,
                            'order_number' => $order->order_number,
                            'reservation_date' => $order->reservation_date,
                        ],
                    ],
                ],
            ];

            $response = $client->post(
                'https://api.paymongo.com/v1/checkout_sessions',
                [
                    'headers' => [
                        'Authorization' => 'Basic ' . base64_encode($secretKey . ':'),
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'json' => $checkoutData,
                ]
            );

            $responseData = json_decode($response->getBody(), true);

            if (!isset($responseData['data']['attributes']['checkout_url'])) {
                Log::error('PayMongo checkout URL missing', $responseData);
                return ['success' => false, 'message' => 'Invalid PayMongo response'];
            }

            return [
                'success' => true,
                'payment_intent_id' => $responseData['data']['id'],
                'checkout_url' => $responseData['data']['attributes']['checkout_url'],
            ];

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = $e->getResponse();
            Log::error('PayMongo API error', [
                'status' => $response?->getStatusCode(),
                'body' => $response?->getBody()?->getContents(),
            ]);

            return [
                'success' => false,
                'message' => 'Payment gateway error. Please try again.',
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

            Log::info('Processing webhook event:', [
                'event_type' => $eventType,
                'resource_type' => $resource['type'] ?? 'unknown'
            ]);

            switch ($eventType) {
                case 'checkout_session.payment.paid':
                    return $this->handleCheckoutSessionPaid($payload);
                    
                case 'checkout_session.payment.failed':
                    return $this->handleCheckoutSessionFailed($payload);
                    
                case 'payment.paid':
                    return $this->handlePaymentPaid($resource);
                    
                case 'payment.failed':
                    return $this->handlePaymentFailed($resource);
                    
                case 'source.chargeable':
                    return $this->handleSourceChargeable($resource);
                    
                default:
                    Log::warning('Unhandled webhook event type:', ['event_type' => $eventType]);
                    return response()->json(['success' => true, 'message' => 'Event ignored']);
            }

        } catch (\Exception $e) {
            Log::error('Webhook processing error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    private function handleCheckoutSessionPaid(array $payload)
    {
        try {
            $eventAttributes = $payload['data']['attributes'] ?? [];
            $sessionData     = $eventAttributes['data'] ?? [];
            $metadata        = $sessionData['attributes']['metadata'] ?? [];

            if (!isset($metadata['order_id'])) {
                Log::error('Checkout session paid but order_id missing', ['payload' => $payload]);
                return response()->json(['success' => false, 'message' => 'Missing order_id'], 400);
            }

            $order = Order::find($metadata['order_id']);

            if (!$order) {
                Log::error('Order not found', ['order_id' => $metadata['order_id']]);
                return response()->json(['success' => false, 'message' => 'Order not found'], 404);
            }

            if ($order->payment_status === 'paid') {
                return response()->json(['success' => true]);
            }

            DB::transaction(function () use ($order, $sessionData) {
                $order->update([
                    'payment_status'             => 'paid',
                    'status'                     => 'processing',
                    'paid_at'                    => now(),
                    'paymongo_payment_intent_id' => $sessionData['id'] ?? null,
                ]);

                if ($order->reservation_date) {
                    ReservationAvailabilityCache::updateForDate(
                        $order->vendor_id,
                        $order->reservation_date
                    );
                }
            });

            // ── Credit vendor balance immediately on payment ──────────────────
            $order->refresh();
            app(\App\Services\VendorFinanceService::class)->handleOrderPayment($order); // ← NEW

            Log::info('Order marked as PAID', [
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
            ]);

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            Log::error('handleCheckoutSessionPaid FAILED', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['success' => false, 'message' => 'Webhook processing failed'], 500);
        }
    }

    private function handlePaymentPaid(array $paymentData)
    {
        $metadata = $paymentData['attributes']['metadata'] ?? [];
        $orderId = $metadata['order_id'] ?? null;
        
        if (!$orderId) {
            Log::error('No order_id in payment metadata', $paymentData);
            return;
        }

        $order = Order::find($orderId);
        if (!$order) {
            Log::error('Order not found for payment', ['order_id' => $orderId]);
            return;
        }

        if (!$this->validateReservationAvailability($order)) {
            Log::error('Reservation no longer available for paid order', ['order_id' => $orderId]);
            return;
        }

        $order->update([
            'payment_status' => 'paid',
            'status' => 'processing',
            'paid_at' => now(),
            'paymongo_payment_id' => $paymentData['id'] ?? null,
        ]);

        if ($order->reservation_date) {
            ReservationAvailabilityCache::updateForDate($order->vendor_id, $order->reservation_date);
        }

        Log::info('Order marked as paid via payment.paid webhook', ['order_id' => $orderId]);
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

    private function handlePaymentFailed($payload)
    {
        $paymentData = $payload['data']['attributes']['data'];
        $orderId = $paymentData['metadata']['order_id'] ?? null;
        
        if ($orderId) {
            $order = Order::find($orderId);
            if ($order) {
                $order->update([
                    'payment_status' => 'failed',
                    'status' => 'payment_failed',
                ]);
                Log::info('Order marked as failed via webhook', ['order_id' => $orderId]);
            }
        }
    }

    private function handleCheckoutSessionFailed($payload)
    {
        return $this->handlePaymentFailed($payload);
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
    

    private function handleSourceChargeable($payload)
    {
        $sourceData = $payload['data']['attributes']['data'];
        $sourceId = $sourceData['id'] ?? null;
        $orderId = $sourceData['metadata']['order_id'] ?? null;
        
        if (!$orderId || !$sourceId) {
            Log::error('Missing order_id or source_id in source.chargeable', $sourceData);
            return;
        }
        
        $order = Order::find($orderId);
        if (!$order) {
            Log::error('Order not found for source.chargeable', ['order_id' => $orderId]);
            return;
        }
        
        $order->update([
            'paymongo_source_id' => $sourceId,
        ]);
        
        Log::info('Source marked as chargeable', ['order_id' => $orderId, 'source_id' => $sourceId]);
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
        $computedSignature = hash_hmac('sha256', $payload, $secretKey);
        
        return hash_equals($signatureHeaderParts['v1'] ?? '', $computedSignature);
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
            $order->update([
                'payment_status' => 'paid',
                'status'         => 'processing',
                'paid_at'        => now(),
            ]);

            // ← redirect to order list, not specific order
            return redirect('/customer/orders')->with('success', 'Payment successful! Your order is now being processed.');
        } else {
            $order->update([
                'payment_status' => 'failed',
                'status'         => 'payment_failed',
            ]);

            return redirect('/customer/checkout')->with('error', 'Payment failed. Please try again.');
        }
    }
}
