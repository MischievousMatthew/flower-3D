<?php
namespace App\Http\Controllers;

use App\Models\BouquetCustomizationOption;
use App\Models\Order;
use App\Support\BouquetPresets;
use App\Models\PayMongoWebhookEvent;
use App\Models\Cart;
use App\Models\Product;
use App\Models\VendorApplication;
use App\Models\VendorClosedDate;
use App\Models\ReservationAvailabilityCache;
use App\Services\VendorFinanceService;
use App\Services\VendorSubscriptionCheckoutService;
use App\Support\PayMongoPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class CheckoutController extends Controller
{
    /** The payment PayMongo reported when confirmPaidCheckoutSession() last saw a paid session */
    private ?array $confirmedPayment = null;

    /**
     * Initialize checkout - now returns vendor delivery fee (no tax)
     */
    public function initializeCheckout(Request $request)
    {
        if ($paused = $this->checkoutPaused()) {
            return $paused;
        }

        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'cart_item_ids' => 'required_without_all:product_id,custom_items|array|min:1',
                'cart_item_ids.*' => 'required|integer|exists:carts,id',
                'product_id' => 'required_without_all:cart_item_ids,custom_items|integer|exists:products,id',
                'quantity' => 'required_with:product_id|integer|min:1',
                'custom_items' => 'required_without_all:cart_item_ids,product_id|array|min:1|max:' . BouquetPresets::MAX_SIZE,
                'custom_items.*.product_id' => 'required|integer|exists:products,id',
                'custom_items.*.quantity' => 'nullable|integer|min:1',
                'custom_items.*.customizations' => 'nullable|array',
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

            if ($request->filled('custom_items')) {
                // Custom bouquets use the same checkout flow, with the design saved on each order item.
                $customItems = collect($request->input('custom_items'));
                $products = Product::with(['primaryImage', 'images', 'owner', 'models'])
                    ->whereIn('id', $customItems->pluck('product_id')->unique())
                    ->get()
                    ->keyBy('id');

                $cartItems = $customItems->map(function ($customItem) use ($products) {
                    $product = $products->get($customItem['product_id']);

                    return (object) [
                        'id' => null,
                        'product_id' => $product->id,
                        'product' => $product,
                        'quantity' => (int) ($customItem['quantity'] ?? 1),
                        'price' => $product->selling_price,
                        'is_available' => true,
                        'color' => null,
                        'size' => null,
                        'notes' => null,
                        'customizations' => $customItem['customizations'] ?? [],
                    ];
                });

                if ($response = $this->applyBouquetExtras($cartItems)) {
                    return $response;
                }
            } elseif ($request->filled('product_id')) {
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

            if ($vendor?->is_suspended) {
                return response()->json([
                    'success' => false,
                    'message' => "This store isn't taking orders right now. Remove its items from your cart to continue.",
                ], 422);
            }

            $takenDown = $cartItems->first(fn ($item) => $item->product?->isTakenDown());
            if ($takenDown) {
                return response()->json([
                    'success' => false,
                    'message' => "{$takenDown->product->product_name} is no longer available. Remove it from your cart to continue.",
                ], 422);
            }

            $vendorApplication = VendorApplication::where('email', $vendor->email)
                ->where('status', 'approved')
                ->first();

            // Shop settings → "Taking orders" switched off
            if ($vendorApplication && !$vendorApplication->isTakingOrders()) {
                return response()->json([
                    'success' => false,
                    'message' => $vendorApplication->pausedOrdersMessage(),
                ], 422);
            }

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

                $isCustomBouquet = data_get($cartItem->customizations, 'type') === 'custom_flower_bouquet';
                $effectivePrice = $isCustomBouquet
                    ? (float) $cartItem->price
                    : ($cartItem->product->discount_price
                        ? (float) $cartItem->product->discount_price
                        : (float) $cartItem->product->selling_price);

                $itemSubtotal = $effectivePrice * $cartItem->quantity;
                $subtotal += $itemSubtotal;

                // Get 3D model if exists
                $model3d = $cartItem->product->models->first();

                $items[] = [
                    'cart_item_id'   => $cartItem->id,
                    'product_id'     => $cartItem->product_id,
                    'product_name'   => $isCustomBouquet ? 'Custom Bouquet' : $cartItem->product->product_name,
                    'product_image'  => $cartItem->product->primary_image->image_url
                                        ?? ($cartItem->product->images[0]->image_url ?? null),
                    'quantity'       => $cartItem->quantity,
                    'unit_price'     => $effectivePrice,           // ← correct
                    'original_price' => $isCustomBouquet ? $effectivePrice : (float) $cartItem->product->selling_price,
                    'discount_price' => !$isCustomBouquet && $cartItem->product->discount_price
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

            $totalAmount = $subtotal;
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
                    'store_address' => $vendorApplication->full_store_address ?? '',
                    'contact_number' => $vendorApplication->contact_number ?? '',
                    'email' => $vendorApplication->email ?? '',
                    'max_orders_per_day' => $vendorApplication?->max_orders_per_day ?? 10,
                    'lead_time' => $vendorApplication?->lead_time ?? '',
                    'lead_time_days' => $vendorApplication?->reservationLeadTimeDays() ?? 0,
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
                    'lead_time_days' => $vendorApplication?->reservationLeadTimeDays() ?? 0,
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
    /** Security → Platform settings can pause all new orders, now or on a schedule */
    private function checkoutPaused(): ?\Illuminate\Http\JsonResponse
    {
        return \App\Support\PlatformSettings::checkoutPausedNow()
            ? response()->json(['success' => false, 'message' => \App\Support\PlatformSettings::get('checkout_pause_message')], 503)
            : null;
    }

    public function createOrder(Request $request)
    {
        if ($paused = $this->checkoutPaused()) {
            return $paused;
        }

        try {
            $user = Auth::user();

            // A payment that was cancelled or failed is finished: it is never
            // paid again. Older pages still send the old order's id; close it
            // and place a new order instead.
            if ($request->filled('retry_order_id')) {
                $old = Order::query()->whereKey($request->input('retry_order_id'))->where('user_id', $user->id)->first();
                if (! $request->filled('reservation_date') && $old?->reservation_date) {
                    $request->merge(['reservation_date' => $old->reservation_date->toDateString()]);
                }
                if ($old) {
                    $this->closeUnfinishedOnlineOrder($old);
                }
                $request->request->remove('retry_order_id');
            }

            // The same checkout sent again (double click, second tab, a retried
            // request) gets the order it already made, never a second one
            $checkoutKey = $this->checkoutKey($request);
            if ($checkoutKey && ($existing = Order::where('user_id', $user->id)->where('checkout_key', $checkoutKey)->first())) {
                return $this->existingOrderResponse($existing);
            }

            $validator = Validator::make($request->all(), [
                'payment_method' => 'required|in:ewallet,cod',
                'delivery_address' => 'required|string',
                'contact_number' => 'required|string',
                'delivery_notes' => 'nullable|string',
                'customer_notes' => 'nullable|string',
                'reservation_date' => 'required|date_format:Y-m-d',
                'checkout_key' => 'nullable|string|max:64',
                'cart_item_ids' => 'required_without_all:product_id,custom_items|array|min:1',
                'product_id' => 'required_without_all:cart_item_ids,custom_items|integer|exists:products,id',
                'quantity' => 'required_with:product_id|integer|min:1',
                'custom_items' => 'required_without_all:cart_item_ids,product_id|array|min:1|max:' . BouquetPresets::MAX_SIZE,
                'custom_items.*.product_id' => 'required|integer|exists:products,id',
                'custom_items.*.quantity' => 'nullable|integer|min:1',
                'custom_items.*.customizations' => 'nullable|array',
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

            if ($request->filled('custom_items')) {
                // Keep custom bouquets inside the normal order/payment pipeline.
                $customItems = collect($request->input('custom_items'));
                $products = Product::with(['owner', 'primaryImage', 'images', 'models'])
                    ->whereIn('id', $customItems->pluck('product_id')->unique())
                    ->get()
                    ->keyBy('id');

                $cartItems = $customItems->map(function ($customItem) use ($products) {
                    $product = $products->get($customItem['product_id']);

                    return (object) [
                        'id' => null,
                        'product_id' => $product->id,
                        'product' => $product,
                        'quantity' => (int) ($customItem['quantity'] ?? 1),
                        'price' => $product->selling_price,
                        'color' => null,
                        'size' => null,
                        'notes' => null,
                        'customizations' => $customItem['customizations'] ?? [],
                    ];
                });

                if ($response = $this->applyBouquetExtras($cartItems)) {
                    return $response;
                }
            } elseif ($request->filled('product_id')) {
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
            $paymentMethod = $this->normalizePaymentMethod($request->payment_method);

            if ($vendorUser?->is_suspended) {
                return response()->json([
                    'success' => false,
                    'message' => "This store isn't taking orders right now. Remove its items from your cart to continue.",
                ], 422);
            }

            $takenDown = $cartItems->first(fn ($item) => $item->product?->isTakenDown());
            if ($takenDown) {
                return response()->json([
                    'success' => false,
                    'message' => "{$takenDown->product->product_name} is no longer available. Remove it from your cart to continue.",
                ], 422);
            }

            $vendor = VendorApplication::where('email', $vendorUser->email)
                ->where('status', 'approved')
                ->first();

            // Cash on Delivery does not use the vendor's online-payment setup.
            // Requiring it here made a COD option appear usable in Checkout but
            // reject the order when it was submitted.
            if (!$vendor || ($paymentMethod === 'ewallet' && !$vendor->payment_details_completed)) {
                return response()->json([
                    'success' => false,
                    'message' => $paymentMethod === 'ewallet'
                        ? 'Vendor is not ready to accept online payments'
                        : 'Vendor is not ready to accept orders'
                ], 400);
            }

            if (!$vendor->isTakingOrders()) {
                return response()->json(['success' => false, 'message' => $vendor->pausedOrdersMessage()], 422);
            }

            if ($paymentMethod === 'cod' && !$vendor->shopPreferences()['accept_cod']) {
                return response()->json([
                    'success' => false,
                    'message' => "{$vendor->store_name} doesn't accept cash on delivery. Please pay with an e-wallet.",
                ], 422);
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
                ->takingSlot()
                ->count();

            if ($ordersCount >= $maxOrders) {
                return response()->json([
                    'success' => false,
                    'message' => 'This date is fully booked. Please select another date.'
                ], 400);
            }

            // E-wallet checkouts left unpaid too long give their stock back first
            $this->releaseExpiredHolds($cartItems->pluck('product_id')->filter()->unique()->all());

            DB::beginTransaction();

            try {
                // Calculate totals (NO TAX)
                $subtotal = 0;
                $itemsData = [];
                
                foreach ($cartItems as $item) {
                    $isCustomBouquet = data_get($item->customizations, 'type') === 'custom_flower_bouquet';
                    $price = $isCustomBouquet
                        ? (float) $item->price
                        : ($item->product->discount_price
                            ? (float) $item->product->discount_price
                            : (float) ($item->product->selling_price ?: $item->price));

                    if (!$price) {
                        throw new \Exception("Price not found for product: " . $item->product->product_name);
                    }

                    $itemSubtotal = $price * $item->quantity;
                    $subtotal += $itemSubtotal;
                    
                    // Get 3D model
                    $model3d = $item->product->models->first();
                    
                    $itemsData[] = [
                        'product_id'          => $item->product_id,
                        'product_name'        => $isCustomBouquet ? 'Custom Bouquet' : ($item->product->product_name ?? 'Unknown Product'),
                        'product_description' => $item->product->description ?? '',
                        'product_image'       => $item->product->primary_image->image_url ??
                                                ($item->product->images[0]->image_url ?? ''),
                        'quantity'            => $item->quantity,
                        'unit_price'          => $price,
                        // The regular price, so finance can show the discount given
                        'list_price'          => $isCustomBouquet
                            ? $price
                            : max($price, (float) ($item->product->selling_price ?: $price)),
                        // What one costs the shop, kept for profit & loss
                        'unit_cost'           => $item->product?->getRawOriginal('purchase_price'),
                        'subtotal'            => $itemSubtotal,
                        'color' => $item->color ?? '',
                        'size' => $item->size ?? '',
                        'notes' => $item->notes ?? '',
                        'customizations' => $item->customizations ?? [],
                        'model_3d_url' => $model3d?->model_url,
                        'model_3d_path' => $model3d?->model_path,
                    ];
                }

                $total = $subtotal;
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
                    'store_address' => $vendor?->full_store_address ?? '',
                    'status' => 'pending',
                    'reservation_date' => $reservationDate->toDateString(), // Store as Y-m-d
                    'checkout_key' => $checkoutKey,
                ]);

                Log::info('Order created with reservation date', [
                    'order_id' => $order->id,
                    'reservation_date' => $order->reservation_date,
                    'input_date' => $request->reservation_date,
                ]);

                // Create order items with 3D model references
                $timestamp = now();
                $orderItems = [];

                foreach ($itemsData as $itemData) {
                    $orderItems[] = [
                        'order_id' => $order->id,
                        'product_id' => $itemData['product_id'],
                        'product_name' => $itemData['product_name'],
                        'product_description' => $itemData['product_description'] ?? null,
                        'product_image' => $itemData['product_image'] ?? null,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'list_price' => $itemData['list_price'] ?? null,
                        'unit_cost' => $itemData['unit_cost'] ?? null,
                        'subtotal' => $itemData['subtotal'],
                        'color' => $itemData['color'] ?? null,
                        'size' => $itemData['size'] ?? null,
                        'notes' => $itemData['notes'] ?? null,
                        'customizations' => json_encode($itemData['customizations'] ?? []),
                        'model_3d_path' => $itemData['model_3d_path'] ?? null,
                        'model_3d_url' => $itemData['model_3d_url'] ?? null,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }

                DB::table('order_items')->insert($orderItems);

                // Every order holds its stock now, e-wallet included: two people
                // can't both pay for the last one. A cancelled or failed payment
                // gives it back. Throws OutOfStockException if it's gone.
                app(VendorFinanceService::class)->deductOrderStock($order);

                // Update reservation cache
                ReservationAvailabilityCache::updateForDate($vendorId, $reservationDate->toDateString());

                DB::commit();

                // Handle payment
                if ($paymentMethod === 'ewallet') {
                    $paymentResponse = $this->createPayMongoPayment($order, $paymentMethod);
                    
                    if ($paymentResponse['success']) {
                        $order->update([
                            'paymongo_payment_intent_id' => $paymentResponse['checkout_session_id'] ?? null,
                            'paymongo_source_id' => $paymentResponse['source_id'] ?? null,
                            'paymongo_checkout_url' => $paymentResponse['checkout_url'] ?? null,
                            'paymongo_response' => [
                                'checkout_session_id' => $paymentResponse['checkout_session_id'] ?? null,
                                'reference_number' => $paymentResponse['reference_number'] ?? null,
                                'payment_attempt' => $paymentResponse['payment_attempt'] ?? null,
                                'payment_method' => $paymentMethod,
                            ],
                        ]);
                        
                        return response()->json([
                            'success' => true,
                            'order_id' => $order->id,
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
                    app(VendorFinanceService::class)->releaseOrderStock($order);

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

                if ($request->filled('cart_item_ids')) {
                    Cart::where('user_id', $user->id)
                        ->whereIn('id', $request->cart_item_ids)
                        ->delete();
                }

                // COD remains available without an online payment confirmation.
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

            } catch (\App\Exceptions\OutOfStockException $e) {
                // Someone else got the last ones first
                DB::rollBack();
                ReservationAvailabilityCache::updateForDate($vendorId, $reservationDate->toDateString());

                return response()->json(['success' => false, 'code' => 'out_of_stock', 'message' => $e->getMessage()], 409);
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                // The same checkout arrived twice at the same moment: answer with the first
                DB::rollBack();
                $existing = $checkoutKey ? Order::where('user_id', $user->id)->where('checkout_key', $checkoutKey)->first() : null;
                if ($existing) {
                    return $this->existingOrderResponse($existing);
                }
                throw $e;
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

    /** The checkout's key (one per checkout attempt; the page makes it) */
    private function checkoutKey(Request $request): ?string
    {
        $key = trim((string) $request->input('checkout_key', ''));

        return preg_match('/^[A-Za-z0-9-]{8,64}$/', $key) ? $key : null;
    }

    /** The answer for a checkout key that already made an order */
    private function existingOrderResponse(Order $order)
    {
        // Still waiting for its e-wallet payment: send them back to the same payment page
        if ($order->payment_method === 'ewallet' && $order->payment_status === 'unpaid'
            && $order->status === 'pending' && $order->paymongo_checkout_url) {
            return response()->json(['success' => true, 'order_id' => $order->id, 'checkout_url' => $order->paymongo_checkout_url, 'duplicate' => true]);
        }

        // Cancelled or failed: finished for good
        if ($order->payment_status === 'failed' || in_array($order->status, ['failed', 'cancelled'], true)) {
            return response()->json([
                'success' => false,
                'code' => 'payment_failed',
                'message' => "This order's payment didn't go through. Place the order again to pay.",
                'data' => ['order' => ['id' => $order->id, 'order_number' => $order->order_number], 'payment_failure' => $order->paymentFailure()],
            ], 409);
        }

        return response()->json([
            'success' => true,
            'duplicate' => true,
            'message' => 'Order created successfully',
            'data' => ['order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'total_amount' => $order->total_amount,
                'payment_method' => $order->payment_method,
                'status' => $order->status,
                'reservation_date' => $order->reservation_date,
            ]],
        ]);
    }

    /**
     * E-wallet checkouts left unpaid past PAYMENT_HOLD_MINUTES give back their
     * stock (checked whenever someone checks out the same products).
     */
    private function releaseExpiredHolds(array $productIds): void
    {
        if (! $productIds) {
            return;
        }

        Order::query()
            ->where('payment_method', 'ewallet')
            ->where('payment_status', 'unpaid')
            ->where('status', 'pending')
            ->whereNotNull('stock_deducted_at')
            ->whereNull('stock_restored_at')
            ->where('created_at', '<', now()->subMinutes(Order::PAYMENT_HOLD_MINUTES))
            ->whereHas('items', fn ($q) => $q->whereIn('product_id', $productIds))
            ->limit(20)
            ->get()
            ->each(fn (Order $order) => $this->closeUnfinishedOnlineOrder($order, 'expired_at'));
    }

    /**
     * Ends an unpaid e-wallet payment for good: cancelled, failed, expired or
     * replaced. Asks PayMongo first (a real payment is kept), stops the PayMongo
     * page from taking a payment after this, and gives the stock back. The
     * order is never paid again; paying means placing a new order.
     *
     * @return string 'paid' when PayMongo had in fact taken the payment, otherwise 'closed'
     */
    private function closeUnfinishedOnlineOrder(Order $order, string $reason = 'cancelled_at', array $extra = []): string
    {
        $order->refresh();
        if ($order->payment_method !== 'ewallet' || $order->payment_status === 'paid') {
            return $order->payment_status === 'paid' ? 'paid' : 'closed';
        }

        if ($order->status === 'pending' && $order->payment_status === 'unpaid' && $this->confirmPaidCheckoutSession($order)) {
            $this->markOrderPaidFromWebhook($order, [
                'checkout_session_id' => $order->paymongo_payment_intent_id,
                'reference_number' => data_get($order->paymongo_response, 'reference_number') ?: $this->buildPayMongoReference($order),
                'payment_attempt' => data_get($order->paymongo_response, 'payment_attempt'),
                'resource' => 'close.paymongo_lookup',
                'payment' => $this->confirmedPayment,
            ]);
            if ($order->fresh()->payment_status === 'paid') {
                return 'paid';
            }
        }

        $this->expirePayMongoSession($order->paymongo_payment_intent_id);

        if (in_array($order->status, ['pending', 'payment_failed', 'failed'], true)) {
            $previous = is_array($order->paymongo_response) ? $order->paymongo_response : [];
            $order->update([
                'payment_status' => 'failed',
                'status' => 'failed',
                'paymongo_payment_intent_id' => null,
                'paymongo_source_id' => null,
                'paymongo_checkout_url' => null,
                'paymongo_response' => array_merge($previous, $extra, [
                    'cancelled_checkout_session_id' => $previous['cancelled_checkout_session_id'] ?? $order->paymongo_payment_intent_id,
                    $reason => $previous[$reason] ?? now()->toIso8601String(),
                ]),
            ]);
        }

        app(VendorFinanceService::class)->releaseOrderStock($order);
        if ($order->reservation_date) {
            ReservationAvailabilityCache::updateForDate($order->vendor_id, $order->reservation_date);
        }

        return 'closed';
    }

    /** Stops a PayMongo checkout page from taking a payment (best effort) */
    private function expirePayMongoSession(?string $sessionId): void
    {
        $secretKey = $this->getPayMongoSecretKey();
        if (! $sessionId || ! $secretKey || ! str_starts_with($sessionId, 'cs_')) {
            return;
        }
        try {
            Http::withBasicAuth($secretKey, '')->acceptJson()->timeout(10)
                ->post('https://api.paymongo.com/v1/checkout_sessions/' . urlencode($sessionId) . '/expire');
        } catch (\Throwable $e) {
            Log::warning('Could not expire PayMongo checkout session', ['session' => $sessionId, 'error' => $e->getMessage()]);
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

        $methods[] = [
            'type' => 'ewallet',
            'name' => 'E-Wallet',
            'description' => 'Choose GCash or Maya securely in PayMongo',
            'icon' => '📱',
            'enabled' => true,
        ];

        // Shop settings → preferences can turn cash on delivery off
        if ($vendorApplication->shopPreferences()['accept_cod']) {
            $methods[] = [
                'type' => 'cod',
                'name' => 'Cash on Delivery',
                'description' => 'Pay when you receive your order',
                'icon' => '💵',
                'enabled' => true,
            ];
        }

        return $methods;
    }

    /**
     * Create PayMongo payment
     */
    private function createPayMongoPayment(Order $order, string $paymentMethod)
    {
        $paymentMethod = $this->normalizePaymentMethod($paymentMethod);
        $secretKey = $this->getPayMongoSecretKey();

        if (!$secretKey) {
            Log::error('PayMongo secret key not configured', [
                'payment_method' => $paymentMethod,
                'order_id' => $order->id,
            ]);
            return ['success' => false, 'message' => 'Payment gateway not configured'];
        }

        if ($paymentMethod !== 'ewallet') {
            return ['success' => false, 'message' => 'Unsupported online payment method.'];
        }

        $frontendUrl = $this->frontendUrl();
        $callbackUrl = url('/api/payment/callback');
        // A unique attempt makes late webhooks from a cancelled/replaced
        // checkout session distinguishable from the active payment session.
        $paymentAttempt = bin2hex(random_bytes(12));
        $referenceNumber = $this->buildPayMongoReference($order, $paymentAttempt);

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
                        'payment_method_types' => ['gcash', 'paymaya'],
                        'success_url' => $callbackUrl
                            . '?success=true&order_id=' . urlencode((string) $order->id)
                            . '&reference=' . urlencode($referenceNumber),
                        'cancel_url' => $callbackUrl
                            . '?success=false&order_id=' . urlencode((string) $order->id)
                            . '&reference=' . urlencode($referenceNumber),
                        'reference_number' => $referenceNumber,
                        'metadata' => [
                            'order_id' => (string) $order->id,
                            'order_number' => $order->order_number,
                            'reference_number' => $referenceNumber,
                            'payment_attempt' => $paymentAttempt,
                            'reservation_date' => $order->reservation_date,
                            'payment_method' => $paymentMethod,
                        ],
                    ],
                ],
            ];

            Log::info('Creating PayMongo checkout session', [
                'order_id' => $order->id,
                'payment_method' => $paymentMethod,
                'checkout_methods' => ['gcash', 'paymaya'],
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
                'payment_attempt' => $paymentAttempt,
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
                'items.product:id,product_name,description',
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
                'data' => $order->toArray() + ['payment_failure' => $order->paymentFailure()],
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
     * Browser Back does not call PayMongo's cancel URL. Explicitly retire the
     * active checkout session when the customer returns to this checkout page.
     * A paid order is deliberately left untouched.
     */
    public function abandonPayment(Request $request, $orderId)
    {
        $order = Order::query()
            ->whereKey($orderId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        if ($order->payment_method !== 'ewallet'
            || $order->payment_status === 'paid'
            || ! in_array($order->status, ['pending', 'payment_failed', 'failed'], true)) {
            return response()->json([
                'success' => true,
                'data' => ['order' => $order],
            ]);
        }

        // Asks PayMongo first, so a payment that did go through is kept
        $this->closeUnfinishedOnlineOrder($order);

        Log::info('PayMongo checkout abandoned by customer return', ['order_id' => $order->id]);

        return response()->json([
            'success' => true,
            'data' => ['order' => $order->fresh()],
        ]);
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

            // Vendor subscriptions use the same configured PayMongo webhook,
            // but are kept separate from customer orders and their records.
            $subscriptionCheckouts = app(VendorSubscriptionCheckoutService::class);
            if ($subscriptionCheckouts->handlesWebhook($payload)) {
                $subscriptionCheckouts->handleWebhook($payload);
                return response()->json(['success' => true, 'status' => 'subscription processed']);
            }

            // Finance paying a supplier for a funding request
            $fundingPayments = app(\App\Services\FundingPayMongoService::class);
            if ($fundingPayments->handlesWebhook($payload)) {
                $fundingPayments->handleWebhook($payload);
                return response()->json(['success' => true, 'status' => 'funding payment processed']);
            }

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
                'payment_attempt' => $metadata['payment_attempt'] ?? null,
                'resource' => 'checkout_session.payment.paid',
                // Amount, fee and net, kept for Finance → Payments
                'payment' => PayMongoPayment::fromEvent($payload),
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
            'checkout_session_id' => $attributes['checkout_session_id']
                ?? data_get($attributes, 'checkout_session.id'),
            'payment_id' => $paymentData['id'] ?? null,
            'reference_number' => $attributes['reference_number']
                ?? $attributes['external_reference_number']
                ?? ($metadata['reference_number'] ?? $metadata['pm_reference_number'] ?? null),
            'payment_attempt' => $metadata['payment_attempt'] ?? null,
            'resource' => 'payment.paid',
            'payment' => PayMongoPayment::fromPaymentResource($paymentData),
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

        if ($order && $this->isActivePayMongoAttempt($order, $paymentData, $attributes, $metadata)) {
            // Keep PayMongo's reason so the customer can see why it didn't go through
            $error = $attributes['last_payment_error'] ?? $attributes;
            // Failed: finished for good, stock back on the shelf
            $this->closeUnfinishedOnlineOrder($order, 'failed_at', array_filter([
                'failed_code' => $error['failed_code'] ?? $error['code'] ?? null,
                'failed_message' => $error['failed_message'] ?? $error['detail'] ?? null,
            ]));
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

            if (!$settings['same_day_available_today']) {
                $cutoff = $settings['cutoff_time_today']
                    ? Carbon::createFromFormat(
                        'H:i',
                        $settings['cutoff_time_today'],
                        $settings['timezone']
                    )->format('g:i A')
                    : null;

                return [
                    'valid' => false,
                    'message' => $settings['same_day_cutoff_reached']
                        ? ($cutoff
                            ? "Same-day delivery cutoff has been reached for today. Cutoff time is {$cutoff} PH time."
                            : 'Same-day delivery cutoff has been reached for today.')
                        : 'Selected date is too early based on preparation time.',
                    'field_error' => $settings['same_day_cutoff_reached']
                        ? ($cutoff
                            ? "Same-day orders are only available until {$cutoff} PH time"
                            : 'Same-day delivery is no longer available today')
                        : 'Preparation starts tomorrow. Please choose a later date.',
                ];
            }

            return ['valid' => true, 'message' => null, 'field_error' => null];
        }

        $minimumDate = $today->copy()->addDays(((int) ($settings['lead_time_days'] ?? 0)) + 1);
        if ($reservationDate->lessThan($minimumDate)) {
            $days = $settings['lead_time_days'];

            return [
                'valid' => false,
                'message' => "Selected date is too early based on lead time. Earliest available date is {$minimumDate->toDateString()}.",
                'field_error' => "Lead time starts tomorrow. Please select a date after {$days} preparation day(s).",
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
        
        if (! $this->isActivePayMongoAttempt($order, $sourceData, $attributes, $metadata)) {
            Log::warning('Ignoring stale PayMongo source event', [
                'order_id' => $order->id,
                'source_id' => $sourceId,
            ]);
            $webhookEvent?->update(['order_id' => $order->id]);
            $webhookEvent?->markAsProcessed();
            return response()->json(['success' => true, 'status' => 'ignored']);
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
        $reference = $request->get('reference');
        $frontendUrl = $this->frontendUrl();

        if (!$orderId && !$reference) {
            return redirect($frontendUrl . '/customer/orders?payment=invalid');
        }

        $order = $orderId ? Order::find($orderId) : null;

        if (!$order && $reference) {
            $order = Order::where('order_number', $reference)->first();
        }

        if (!$order) {
            return redirect($frontendUrl . '/customer/orders?payment=missing');
        }

        if ($success) {
            // A browser redirect is not proof of payment. Confirm the session
            // directly with PayMongo; the signed webhook remains the primary
            // asynchronous confirmation path.
            if ($order->payment_status !== 'paid' && $this->confirmPaidCheckoutSession($order)) {
                $this->markOrderPaidFromWebhook($order, [
                    'checkout_session_id' => $order->paymongo_payment_intent_id,
                    'reference_number' => $reference ?: $this->buildPayMongoReference($order),
                    'payment_attempt' => data_get($order->paymongo_response, 'payment_attempt'),
                    'resource' => 'payment.callback.paymongo_lookup',
                    'payment' => $this->confirmedPayment,
                ]);
            }

            $paymentState = $order->fresh()->payment_status === 'paid'
                ? 'success'
                : 'pending';

            if ($paymentState === 'success') {
                return redirect(
                    $frontendUrl . '/customer/orders?payment=success&reference='
                    . urlencode($reference ?: $this->buildPayMongoReference($order))
                );
            }

            // Do not present an unconfirmed payment as an order. Return to
            // payment instead; Checkout will retire this unfinished attempt.
            return redirect(
                $frontendUrl . '/customer/checkout?payment=pending&order_id='
                . urlencode((string) $order->id)
            );
        }

        // A cancelled browser checkout is never a payment confirmation. Clear
        // its active session so a delayed event from that session cannot later
        // be attached to this order; the same order remains retryable.
        $activeReference = data_get($order->paymongo_response, 'reference_number');
        if ($order->payment_method === 'ewallet'
            && $order->payment_status !== 'paid'
            && in_array($order->status, ['pending', 'payment_failed', 'failed'], true)
            // An old browser tab may return its cancel URL after a retry has
            // begun. It must not cancel the retry's active session.
            && (!$reference || !$activeReference || hash_equals($activeReference, $reference))) {
            // Cancelled: finished for good, stock back on the shelf
            if ($this->closeUnfinishedOnlineOrder($order) === 'paid') {
                return redirect($frontendUrl . '/customer/orders?payment=success&reference=' . urlencode($reference ?: $this->buildPayMongoReference($order)));
            }
        }

        return redirect(
            $frontendUrl . '/customer/checkout?payment=cancelled&order_id='
            . urlencode((string) $order->id)
        );
    }

    private function buildPayMongoReference(Order $order, ?string $paymentAttempt = null): string
    {
        return $paymentAttempt ? $order->order_number . '-' . $paymentAttempt : $order->order_number;
    }

    /**
     * Confirms a completed Checkout Session with PayMongo's authenticated API.
     * This is intentionally used only after the return URL and never treats a
     * redirect itself as payment confirmation.
     */
    private function confirmPaidCheckoutSession(Order $order): bool
    {
        $sessionId = $order->paymongo_payment_intent_id;
        $secretKey = $this->getPayMongoSecretKey();

        if (!$sessionId || !$secretKey) {
            return false;
        }

        try {
            $response = Http::withBasicAuth($secretKey, '')
                ->acceptJson()
                ->timeout(15)
                ->get('https://api.paymongo.com/v1/checkout_sessions/' . urlencode($sessionId));

            if (!$response->successful()) {
                Log::warning('Unable to confirm PayMongo checkout session', [
                    'order_id' => $order->id,
                    'checkout_session_id' => $sessionId,
                    'status' => $response->status(),
                ]);
                return false;
            }

            $attributes = data_get($response->json(), 'data.attributes', []);
            $statuses = collect([
                data_get($attributes, 'status'),
                data_get($attributes, 'payment.status'),
                data_get($attributes, 'payment.attributes.status'),
                data_get($attributes, 'payment_intent.status'),
                data_get($attributes, 'payment_intent.attributes.status'),
            ])
                ->merge(collect(data_get($attributes, 'payments', []))->map(
                    fn ($payment) => data_get($payment, 'attributes.status', data_get($payment, 'status'))
                ))
                ->filter()
                ->map(fn ($status) => strtolower((string) $status));

            $isPaid = $statuses->contains(
                fn ($status) => in_array($status, ['paid', 'succeeded'], true)
            );
            Log::info('PayMongo checkout session confirmation result', [
                'order_id' => $order->id,
                'checkout_session_id' => $sessionId,
                'statuses' => $statuses->values()->all(),
                'is_paid' => $isPaid,
            ]);

            // Amount, fee and net of the payment, saved with the order when it's marked paid
            $payment = data_get($attributes, 'payments.0');
            $this->confirmedPayment = $isPaid && is_array($payment) ? PayMongoPayment::fromPaymentResource($payment) : null;

            return $isPaid;
        } catch (\Throwable $e) {
            Log::warning('PayMongo checkout session confirmation failed', [
                'order_id' => $order->id,
                'checkout_session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function getPayMongoSecretKey(): ?string
    {
        return config('services.paymongo.secret_key') ?: env('PAYMONGO_SECRET_KEY');
    }

    private function frontendUrl(): string
    {
        $frontendUrl = trim((string) config('app.frontend_url', 'https://bloomcraft-app.vercel.app'));

        if ($frontendUrl === '') {
            return 'https://bloomcraft-app.vercel.app';
        }

        if (!preg_match('#^https?://#i', $frontendUrl)) {
            $frontendUrl = 'https://' . ltrim($frontendUrl, '/');
        }

        return rtrim($frontendUrl, '/');
    }

    /**
     * A direct custom-bouquet checkout sends one item per flower. Price the
     * vendor's wrapper, ribbon and fillers once, on the first item, and save
     * the server-resolved choices on every item.
     */
    private function applyBouquetExtras($cartItems)
    {
        $first = $cartItems->first();
        if (!$first || data_get($first->customizations, 'type') !== 'custom_flower_bouquet') {
            return null;
        }

        $extras = BouquetCustomizationOption::resolveSelections(
            (int) $first->product->owner_id,
            (array) $first->customizations
        );

        if (!$extras['ok']) {
            return response()->json(['success' => false, 'message' => $extras['message']], 422);
        }

        $cartItems->each(fn ($item) => $item->customizations = $extras['customizations']);
        $first->price = (float) $first->price + $extras['total'];

        return null;
    }

    private function normalizePaymentMethod(?string $paymentMethod): ?string
    {
        return match ($paymentMethod) {
            'paymaya', 'gcash', 'maya' => 'ewallet',
            default => $paymentMethod,
        };
    }

    private function hasPayMongoConfiguration(): bool
    {
        return !empty($this->getPayMongoSecretKey());
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

    /**
     * Match non-paid events to the active checkout attempt as well. Without
     * this, a delayed failure from an abandoned session could fail a newer
     * retry, or a delayed source event could overwrite its payment details.
     */
    private function isActivePayMongoAttempt(
        Order $order,
        array $resource,
        array $attributes,
        array $metadata = []
    ): bool {
        if ($order->payment_method !== 'ewallet'
            || $order->payment_status !== 'unpaid'
            || $order->status !== 'pending'
            || ! $order->paymongo_payment_intent_id) {
            return false;
        }

        $expectedAttempt = data_get($order->paymongo_response, 'payment_attempt');
        $receivedAttempt = $metadata['payment_attempt'] ?? null;
        $receivedSession = $resource['id']
            ?? $attributes['checkout_session_id']
            ?? data_get($attributes, 'checkout_session.id');

        if ($receivedSession && $receivedSession === $order->paymongo_payment_intent_id) {
            return ! $expectedAttempt || $receivedAttempt === $expectedAttempt;
        }

        return (bool) ($expectedAttempt && $receivedAttempt === $expectedAttempt);
    }

    private function markOrderPaidFromWebhook(Order $order, array $paymentContext = []): bool
    {
        $markedPaid = DB::transaction(function () use ($order, $paymentContext): bool {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->first();
            if (! $lockedOrder) {
                return false;
            }

            if ($lockedOrder->payment_status === 'paid') {
                return true;
            }

            // Payment events must belong to the currently active checkout
            // attempt. This rejects events delivered after a cancellation and
            // events from an older retry attempt.
            $response = is_array($lockedOrder->paymongo_response)
                ? $lockedOrder->paymongo_response
                : [];
            $expectedSession = $lockedOrder->paymongo_payment_intent_id;
            $expectedAttempt = $response['payment_attempt'] ?? null;
            $receivedSession = $paymentContext['checkout_session_id'] ?? null;
            $receivedAttempt = $paymentContext['payment_attempt'] ?? null;

            if ($lockedOrder->payment_method !== 'ewallet'
                || $lockedOrder->payment_status !== 'unpaid'
                || $lockedOrder->status !== 'pending'
                || ! $expectedSession
                || (($receivedSession && $receivedSession !== $expectedSession)
                    || ($expectedAttempt && $receivedAttempt !== $expectedAttempt)
                    || (! $receivedSession && ! $receivedAttempt))) {
                // Paid on a payment page this order had already closed (cancelled,
                // failed or expired): the order stays closed, so mark the money
                // for a refund instead of losing track of it
                $closedSession = $response['cancelled_checkout_session_id'] ?? null;
                if ($lockedOrder->status === 'failed' && $receivedSession && $closedSession === $receivedSession) {
                    $lockedOrder->update(['paymongo_response' => array_merge($response, [
                        'paid_after_close' => now()->toIso8601String(),
                        'needs_refund' => true,
                    ])]);
                    Log::error('PayMongo payment arrived for a closed order: refund needed', ['order_id' => $lockedOrder->id]);
                    return false;
                }

                Log::warning('Ignoring stale or inactive PayMongo paid event', [
                    'order_id' => $lockedOrder->id,
                    'expected_session' => $expectedSession,
                    'received_session' => $receivedSession,
                    'expected_attempt' => $expectedAttempt,
                    'received_attempt' => $receivedAttempt,
                ]);
                return false;
            }

            $updates = [
                'payment_status' => 'paid',
                'paid_at' => $lockedOrder->paid_at ?? now(),
            ];

            $updates['status'] = 'processing';
            $paymongoResponse = $response;

            $lockedOrder->update(array_merge($updates, [
                'paymongo_payment_intent_id' => $paymentContext['checkout_session_id']
                    ?? $paymentContext['payment_id']
                    ?? $lockedOrder->paymongo_payment_intent_id,
                'paymongo_response' => array_filter(array_merge($paymongoResponse, [
                    'reference_number' => $paymentContext['reference_number'] ?? null,
                    'webhook_resource' => $paymentContext['resource'] ?? null,
                    'paid_at' => now()->toIso8601String(),
                ], $paymentContext['payment'] ?? []), fn ($value) => $value !== null),
            ]));

            if ($lockedOrder->reservation_date) {
                ReservationAvailabilityCache::updateForDate(
                    $lockedOrder->vendor_id,
                    $lockedOrder->reservation_date
                );
            }

            if ($lockedOrder->payment_method === 'ewallet') {
                $productIds = $lockedOrder->items()->pluck('product_id')->filter();
                if ($productIds->isNotEmpty()) {
                    Cart::where('user_id', $lockedOrder->user_id)
                        ->whereIn('product_id', $productIds)
                        ->delete();
                }
            }

            // The payment confirmation and inventory commitment belong to the
            // same database transaction. The service lock/timestamp makes
            // repeated webhook deliveries harmless.
            app(VendorFinanceService::class)->deductOrderStock($lockedOrder);
            return true;
        });

        if ($markedPaid) {
            app(VendorFinanceService::class)->handleOrderPayment($order->fresh());
        }

        return $markedPaid;
    }
}
