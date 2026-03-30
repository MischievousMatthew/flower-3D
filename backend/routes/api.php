<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Customer\CustomerProductController;
use App\Http\Controllers\Admin\VendorApplicationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\VendorProfileController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\VendorOrdersController;
use App\Http\Controllers\EmployeeAuthController;
use App\Http\Controllers\EmployeeInfoController;
use App\Http\Controllers\FundingRequestController;
use App\Http\Controllers\AccountingFundingRequestController;
use App\Http\Controllers\EmployeeQRController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\EmployeeLeaveController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\WarehouseBatchController;
use App\Http\Controllers\WarehouseLocationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DeliveryBarcodeController;
use App\Http\Controllers\VendorStorefrontController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\CustomerOrderTrackingController;
use App\Http\Controllers\OrderRequestController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\VendorFinanceDashboardController;
use App\Http\Controllers\ProductReportController; // ← NEW

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ============================================================
// 1. PUBLIC — AUTHENTICATION
// ============================================================

Route::prefix('auth')->group(function () {
    Route::post('/send-otp',     [AuthController::class, 'sendOtp']);
    Route::post('/register',     [AuthController::class, 'register']);
    Route::post('/login',        [AuthController::class, 'login']);
    Route::post('/vendor/login', [AuthController::class, 'vendorLogin']);
});

Route::post('/auth/employee-login', [EmployeeAuthController::class, 'login']);

// ============================================================
// 2. PUBLIC — VENDOR REGISTRATION
// ============================================================

Route::post('/vendor/register', [VendorController::class, 'register']);
Route::get('/vendor/status',    [VendorController::class, 'checkStatus']);

// ============================================================
// 3. PUBLIC — CUSTOMER STOREFRONT
// ============================================================

Route::prefix('customer')->group(function () {
    Route::get('/products',                     [CustomerProductController::class, 'index']);
    Route::get('/products/featured',            [CustomerProductController::class, 'featured']);
    Route::get('/products/new-arrivals',        [CustomerProductController::class, 'newArrivals']);
    Route::get('/products/on-sale',             [CustomerProductController::class, 'onSale']);
    Route::get('/products/search',              [CustomerProductController::class, 'search']);
    Route::get('/products/categories',          [CustomerProductController::class, 'categories']);
    Route::get('/products/filters',             [CustomerProductController::class, 'filters']);
    Route::get('/products/category/{category}', [CustomerProductController::class, 'byCategory']);
    Route::get('/products/{id}',                [CustomerProductController::class, 'show']);
    Route::get('/products/{id}/related',        [CustomerProductController::class, 'related']);
    Route::get('/product-models/{filename}',    [CustomerProductController::class, 'serveModel']);
    Route::get('/availability',                 [ReservationController::class, 'getAvailability']);
    Route::post('/check-date',                  [ReservationController::class, 'checkDateAvailability']);
});

// ── Public vendor storefront ──────────────────────────────────────────────
Route::prefix('vendors')->group(function () {
    Route::get('/',                    [VendorStorefrontController::class, 'index']);
    Route::get('/{id}',                [VendorStorefrontController::class, 'show']);
    Route::get('/{vendorId}/products', [VendorStorefrontController::class, 'getProducts']);
});

// ── PUBLIC — Product reviews (no auth required) ───────────────────────────
Route::get('/products/{productId}/reviews', [ProductReviewController::class, 'index'])
    ->whereNumber('productId');

// ============================================================
// 4. PUBLIC — PAYMONGO WEBHOOK
// ============================================================

Route::post('/paymongo/webhook', [CheckoutController::class, 'handleWebhook'])
    ->middleware('paymongo.webhook');
Route::get('/payment/callback',  [CheckoutController::class, 'paymentCallback']);

// ============================================================
// 5. PUBLIC — EMPLOYEE LEAVE (QR token, no auth)
// ============================================================

Route::prefix('public/leave')->group(function () {
    Route::post('/verify-qr', [EmployeeLeaveController::class, 'verifyQRToken']);
    Route::post('/submit',    [EmployeeLeaveController::class, 'submitLeaveRequest']);
});

// ============================================================
// 6. AUTHENTICATED ROUTES (auth:sanctum)
// ============================================================

Route::middleware('auth:sanctum')->group(function () {

    // ----------------------------------------------------------
    // 6a. Auth — session management
    // ----------------------------------------------------------

    Route::get('/auth/me',               [AuthController::class,         'me']);
    Route::post('/auth/logout',          [AuthController::class,         'logout']);
    Route::get('/auth/employee-me',      [EmployeeAuthController::class, 'me']);
    Route::post('/auth/employee-logout', [EmployeeAuthController::class, 'logout']);

    // ----------------------------------------------------------
    // 6b. Profile
    // ----------------------------------------------------------

    Route::prefix('profile')->group(function () {
        Route::get('/user-profile', [ProfileController::class, 'getProfile']);
        Route::put('/user-details', [ProfileController::class, 'updateProfile']);
        Route::post('/picture',     [ProfileController::class, 'updateProfilePicture']);
    });

    // ----------------------------------------------------------
    // 6c. Checkout & Cart
    // ----------------------------------------------------------

    Route::prefix('checkout')->group(function () {
        Route::post('/initialize',      [CheckoutController::class, 'initializeCheckout']);
        Route::post('/create-order',    [CheckoutController::class, 'createOrder']);
        Route::get('/orders/{orderId}', [CheckoutController::class, 'getOrder']);
        Route::get('/payment-callback', [CheckoutController::class, 'paymentCallback']);
    });

    Route::prefix('cart')->group(function () {
        Route::get('/',               [CartController::class, 'index']);
        Route::get('/summary',        [CartController::class, 'getSummary']);
        Route::get('/validate',       [CartController::class, 'validateCart']);
        Route::post('/add',           [CartController::class, 'addToCart']);
        Route::put('/update/{id}',    [CartController::class, 'updateCartItem'])->whereNumber('id');
        Route::delete('/remove/{id}', [CartController::class, 'removeFromCart'])->whereNumber('id');
        Route::delete('/clear',       [CartController::class, 'clearCart']);
    });

    // ----------------------------------------------------------
    // 6d. Customer — Order tracking + delivery + reviews + reports
    // ----------------------------------------------------------

    Route::prefix('customer')->group(function () {

        // ── Order tracking ────────────────────────────────────────────────────
        Route::get('/orders',      [CustomerOrderTrackingController::class, 'index']);
        Route::get('/orders/{id}', [CustomerOrderTrackingController::class, 'show'])->whereNumber('id');

        Route::post('/orders/{id}/complete',         [CustomerOrderTrackingController::class, 'complete'])->whereNumber('id');
        Route::post('/orders/{id}/confirm-received', [CustomerOrderTrackingController::class, 'confirmReceived'])->whereNumber('id');
        Route::post('/orders/{id}/request-return',   [CustomerOrderTrackingController::class, 'requestReturn'])->whereNumber('id');
        Route::post('/orders/{id}/request-refund',   [CustomerOrderTrackingController::class, 'requestRefund'])->whereNumber('id');
        Route::get('/{orderId}/delivery',            [DeliveryController::class, 'customerOrder']);

        // ── Reviews ───────────────────────────────────────────────────────────
        Route::get('/reviewable-products',              [ProductReviewController::class, 'reviewableProducts']);
        Route::post('/products/{productId}/review',     [ProductReviewController::class, 'store'])
            ->whereNumber('productId');
    });

    // ── Customer — Report a product ───────────────────────────────────────
    // POST /api/products/{id}/report
    Route::post('/products/{id}/report', [ProductReportController::class, 'store'])
        ->whereNumber('id');                                                          // ← NEW

    // ── Standalone review CRUD ────────────────────────────────────────────
    Route::put('/reviews/{id}',    [ProductReviewController::class, 'update'])->whereNumber('id');
    Route::delete('/reviews/{id}', [ProductReviewController::class, 'destroy'])->whereNumber('id');

    // ── Vendor follow/unfollow ────────────────────────────────────────────
    Route::post('/vendors/{vendorId}/follow',   [VendorStorefrontController::class, 'follow']);
    Route::post('/vendors/{vendorId}/unfollow', [VendorStorefrontController::class, 'unfollow']);

    // ── Chat ─────────────────────────────────────────────────────────────
    Route::prefix('chat')->group(function () {
        Route::get('/conversations',                  [ChatController::class, 'getConversations']);
        Route::get('/my-conversations',               [ChatController::class, 'getConversations']);
        Route::get('/conversations/{id}/messages',    [ChatController::class, 'getMessages']);
        Route::post('/messages/send',                 [ChatController::class, 'sendMessage']);
        Route::get('/poll',                           [ChatController::class, 'pollNewMessages']);
        Route::post('/conversations/start',           [ChatController::class, 'startConversation']);
        Route::get('/search-users',                   [ChatController::class, 'searchUsers']);
        Route::get('/search-customers',               [ChatController::class, 'searchUsers']);
        Route::get('/search-vendors',                 [ChatController::class, 'searchUsers']);
        Route::get('/customer/{id}/details',          [ChatController::class, 'getUserDetails']);
        Route::get('/vendor/{id}/details',            [ChatController::class, 'getUserDetails']);
    });

    // ----------------------------------------------------------
    // 6e. Vendor portal
    // ----------------------------------------------------------

    Route::prefix('vendor')->group(function () {

        Route::get('/dashboard', fn () => response()->json(['message' => 'Vendor dashboard']));

        Route::prefix('employees')->group(function () {
            Route::get('/',              [EmployeeController::class, 'index']);
            Route::get('/statistics',    [EmployeeController::class, 'statistics']);
            Route::get('/search',        [EmployeeController::class, 'search']);
            Route::post('/',             [EmployeeController::class, 'store']);
            Route::get('/{id}',          [EmployeeController::class, 'show']);
            Route::put('/{id}',          [EmployeeController::class, 'update']);
            Route::patch('/{id}',        [EmployeeController::class, 'update']);
            Route::patch('/{id}/status', [EmployeeController::class, 'updateStatus']);
            Route::delete('/{id}',       [EmployeeController::class, 'destroy']);
            Route::get('/{id}/assignments',                              [AssignmentController::class, 'index']);
            Route::post('/{id}/assignments',                             [AssignmentController::class, 'store']);
            Route::patch('/{id}/assignments/{assignmentId}/set-primary', [AssignmentController::class, 'setPrimary']);
            Route::delete('/{id}/assignments/{assignmentId}',            [AssignmentController::class, 'destroy']);
        });

        Route::get('/departments', [AssignmentController::class, 'departments']);

        Route::prefix('profile')->group(function () {
            Route::get('/',                 [VendorProfileController::class, 'getProfile']);
            Route::put('/payment-details',  [VendorProfileController::class, 'updatePaymentDetails']);
            Route::put('/product-details',  [VendorProfileController::class, 'updateProductDetails']);
            Route::put('/delivery-details', [VendorProfileController::class, 'updateDeliveryDetails']);
            Route::put('/general-info',     [VendorProfileController::class, 'updateGeneralInfo']);
            Route::post('/store-logo',      [VendorProfileController::class, 'updateStoreLogo']);
            Route::delete('/store-logo',    [VendorProfileController::class, 'deleteStoreLogo']);
            Route::put('/change-password',  [VendorProfileController::class, 'changePassword']);
        });

        Route::get('/products',            [ProductController::class, 'myProducts']);
        Route::get('/my-products',         [ProductController::class, 'myProducts']);
        Route::get('/products/draft',      [ProductController::class, 'draftProducts']);
        Route::get('/draft-products',      [ProductController::class, 'draftProducts']);
        Route::get('/products/inactive',   [ProductController::class, 'inactiveProducts']);
        Route::get('/inactive-products',   [ProductController::class, 'inactiveProducts']);
        Route::post('/products',           [ProductController::class, 'store']);
        Route::get('/products/{id}',       [ProductController::class, 'show']);
        Route::put('/products/{id}',       [ProductController::class, 'update']);
        Route::patch('/products/{id}',     [ProductController::class, 'update']);
        Route::delete('/products/{id}',    [ProductController::class, 'destroy']);
        Route::post('/products/{id}/toggle-status',             [ProductController::class, 'toggleStatus']);
        Route::post('/products/{id}/status',                    [ProductController::class, 'updateStatus']);
        Route::patch('/products/{id}/stock',                    [ProductController::class, 'updateStock']);
        Route::post('/products/{id}/update-stock',              [ProductController::class, 'updateStock']);
        Route::delete('/products/{productId}/images/{imageId}', [ProductController::class, 'deleteImage']);
        Route::delete('/products/{productId}/model',            [ProductController::class, 'deleteModel']);

        Route::get('/products/{productId}/reviews', [ProductReviewController::class, 'vendorProductReviews'])
            ->whereNumber('productId');

        Route::get('/orders',                  [VendorOrdersController::class, 'getAllOrders']);
        Route::get('/orders/statistics',       [VendorOrdersController::class, 'getOrderStatistics']);
        Route::get('/orders/calendar-data',    [VendorOrdersController::class, 'getCalendarData']);
        Route::get('/orders/for-date',         [VendorOrdersController::class, 'getOrdersForDate']);
        Route::get('/orders/{orderId}',        [VendorOrdersController::class, 'getOrderDetails']);
        Route::put('/orders/{orderId}/status', [VendorOrdersController::class, 'updateOrderStatus']);

        Route::prefix('reservations')->group(function () {
            Route::get('/calendar',             [VendorOrdersController::class, 'getCalendarData']);
            Route::get('/closed-dates',         [VendorOrdersController::class, 'getClosedDates']);
            Route::post('/close-date',          [VendorOrdersController::class, 'markDateAsClosed']);
            Route::delete('/close-date/{id}',   [VendorOrdersController::class, 'removeClosedDate']);
            Route::get('/orders/calendar-data', [VendorOrdersController::class, 'getCalendarData']);
        });

        Route::post('/orders/{orderId}/scan-delivery', [DeliveryController::class, 'scan']);
        Route::get('/deliveries',                      [DeliveryController::class, 'vendorOrders']);

        Route::prefix('finance')->group(function () {
            Route::get('/overview',     [VendorFinanceDashboardController::class, 'overview']);
            Route::get('/transactions', [VendorFinanceDashboardController::class, 'transactions']);
            Route::get('/cashflow',     [VendorFinanceDashboardController::class, 'cashflow']);
        });
    });

    // ----------------------------------------------------------
    // 6f. SC Coordinator portal
    // ----------------------------------------------------------

    Route::prefix('sc')->group(function () {
        Route::get('/orders',                   [DeliveryController::class,        'scOrders']);
        Route::post('/barcode/scan',            [DeliveryController::class,        'scan']);
        Route::get('/orders/{orderId}/barcode', [DeliveryBarcodeController::class, 'show']);
        Route::get('/order-requests',                        [OrderRequestController::class, 'index']);
        Route::post('/order-requests/{id}/approve',          [OrderRequestController::class, 'approve']);
        Route::post('/order-requests/{id}/reject',           [OrderRequestController::class, 'reject']);
    });

    // ----------------------------------------------------------
    // 6g. Delivery management
    // ----------------------------------------------------------

    Route::prefix('deliveries')->group(function () {
        Route::patch('/{id}/status', [DeliveryController::class, 'updateStatus']);
        Route::get('/{id}/logs',     [DeliveryController::class, 'logs']);
    });

    // ----------------------------------------------------------
    // 6h. HR — Employee info, attendance, QR, payroll, leave
    // ----------------------------------------------------------

    Route::prefix('employees-info')->group(function () {
        Route::get('/',              [EmployeeInfoController::class, 'index']);
        Route::get('/statistics',    [EmployeeInfoController::class, 'statistics']);
        Route::post('/',             [EmployeeInfoController::class, 'store']);
        Route::get('/{id}',          [EmployeeInfoController::class, 'show']);
        Route::put('/{id}',          [EmployeeInfoController::class, 'update']);
        Route::patch('/{id}/status', [EmployeeInfoController::class, 'updateStatus']);
        Route::delete('/{id}',       [EmployeeInfoController::class, 'destroy']);
    });

    Route::prefix('attendance')->group(function () {
        Route::post('/scan',     [AttendanceController::class, 'scanQR']);
        Route::get('/',          [AttendanceController::class, 'index']);
        Route::get('/summary',   [AttendanceController::class, 'summary']);
        Route::post('/',         [AttendanceController::class, 'store']);
        Route::put('/{id}',      [AttendanceController::class, 'update']);
        Route::delete('/{id}',   [AttendanceController::class, 'destroy']);
        Route::post('/time-in',  [AttendanceController::class, 'timeIn']);
        Route::post('/time-out', [AttendanceController::class, 'timeOut']);
    });

    Route::prefix('employees/{employeeId}/qr-code')->group(function () {
        Route::post('/generate', [EmployeeQRController::class, 'generate']);
        Route::get('/svg',       [EmployeeQRController::class, 'getSVG']);
        Route::get('/base64',    [EmployeeQRController::class, 'getBase64']);
        Route::get('/download',  [EmployeeQRController::class, 'download']);
    });

    Route::prefix('payroll')->group(function () {
        Route::get('/',                 [PayrollController::class, 'index']);
        Route::post('/',                [PayrollController::class, 'store']);
        Route::post('/preview',         [PayrollController::class, 'preview']);
        Route::get('/summary',          [PayrollController::class, 'summary']);
        Route::post('/hr-approve',      [PayrollController::class, 'hrApprove']);
        Route::get('/finance-requests', [PayrollController::class, 'financeRequests']);
        Route::get('/finance-summary',  [PayrollController::class, 'financeSummary']);
        Route::post('/finance-approve', [PayrollController::class, 'financeApprove']);
        Route::post('/finance-reject',  [PayrollController::class, 'financeReject']);
        Route::post('/mark-paid',       [PayrollController::class, 'markAsPaid']);
        Route::get('/{id}',             [PayrollController::class, 'show']);
        Route::delete('/{id}',          [PayrollController::class, 'destroy']);
    });

    Route::prefix('leaves')->group(function () {
        Route::get('/',            [EmployeeLeaveController::class, 'index']);
        Route::get('/statistics',  [EmployeeLeaveController::class, 'getStatistics']);
        Route::put('/{id}/status', [EmployeeLeaveController::class, 'updateStatus']);
        Route::delete('/{id}',     [EmployeeLeaveController::class, 'destroy']);
    });

    // ----------------------------------------------------------
    // 6i. Procurement — Inventory Manager
    // ----------------------------------------------------------

    Route::prefix('procurement/inventory')->group(function () {
        Route::get('/products',          [ProductController::class, 'myProducts']);
        Route::get('/my-products',       [ProductController::class, 'myProducts']);
        Route::get('/products/draft',    [ProductController::class, 'draftProducts']);
        Route::get('/draft-products',    [ProductController::class, 'draftProducts']);
        Route::get('/products/inactive', [ProductController::class, 'inactiveProducts']);
        Route::get('/inactive-products', [ProductController::class, 'inactiveProducts']);
        Route::get('/products/search',   [ProductController::class, 'searchForWarehouse']);
        Route::post('/products',         [ProductController::class, 'store']);
        Route::get('/products/{id}',     [ProductController::class, 'show']);
        Route::put('/products/{id}',     [ProductController::class, 'update']);
        Route::patch('/products/{id}',   [ProductController::class, 'update']);
        Route::delete('/products/{id}',  [ProductController::class, 'destroy']);
        Route::post('/products/{id}/toggle-status',             [ProductController::class, 'toggleStatus']);
        Route::post('/products/{id}/status',                    [ProductController::class, 'updateStatus']);
        Route::patch('/products/{id}/stock',                    [ProductController::class, 'updateStock']);
        Route::post('/products/{id}/update-stock',              [ProductController::class, 'updateStock']);
        Route::delete('/products/{productId}/images/{imageId}', [ProductController::class, 'deleteImage']);
        Route::delete('/products/{productId}/model',            [ProductController::class, 'deleteModel']);
        Route::get('accounting-managers',           [FundingRequestController::class, 'getAccountingManagers']);
        Route::get('funding-requests/products',     [FundingRequestController::class, 'getProducts']);
        Route::get('funding-requests',              [FundingRequestController::class, 'index']);
        Route::post('funding-requests',             [FundingRequestController::class, 'store']);
        Route::get('funding-requests/{id}',         [FundingRequestController::class, 'show']);
        Route::put('funding-requests/{id}',         [FundingRequestController::class, 'update']);
        Route::delete('funding-requests/{id}',      [FundingRequestController::class, 'destroy']);
        Route::post('funding-requests/{id}/submit', [FundingRequestController::class, 'submitToAccounting']);
    });

    // ----------------------------------------------------------
    // 6j. Procurement — Supply Chain Coordinator
    // ----------------------------------------------------------

    Route::prefix('procurement/supply-chain')->group(function () {

        Route::prefix('suppliers')->group(function () {
            Route::get('/',               [SupplierController::class, 'index']);
            Route::post('/',              [SupplierController::class, 'store']);
            Route::get('/{id}',           [SupplierController::class, 'show']);
            Route::put('/{id}',           [SupplierController::class, 'update']);
            Route::patch('/{id}',         [SupplierController::class, 'update']);
            Route::delete('/{id}',        [SupplierController::class, 'destroy']);
            Route::patch('/{id}/activate',   [SupplierController::class, 'activate']);
            Route::patch('/{id}/deactivate', [SupplierController::class, 'deactivate']);
            Route::patch('/{id}/blacklist',  [SupplierController::class, 'blacklist']);
            Route::get('/{supplierId}/contacts',         [SupplierController::class, 'contacts']);
            Route::post('/{supplierId}/contacts',        [SupplierController::class, 'storeContact']);
            Route::put('/{supplierId}/contacts/{id}',    [SupplierController::class, 'updateContact']);
            Route::delete('/{supplierId}/contacts/{id}', [SupplierController::class, 'destroyContact']);
        });

        Route::prefix('warehouses')->group(function () {
            Route::get('/',        [WarehouseController::class, 'index']);
            Route::post('/',       [WarehouseController::class, 'store']);
            Route::get('/{id}',    [WarehouseController::class, 'show']);
            Route::put('/{id}',    [WarehouseController::class, 'update']);
            Route::patch('/{id}',  [WarehouseController::class, 'update']);
            Route::delete('/{id}', [WarehouseController::class, 'destroy']);
            Route::get('/{warehouseId}/items',              [WarehouseController::class, 'items']);
            Route::post('/{warehouseId}/items',             [WarehouseController::class, 'addItem']);
            Route::put('/{warehouseId}/items/{id}',         [WarehouseController::class, 'updateItem']);
            Route::patch('/{warehouseId}/items/{id}/stock', [WarehouseController::class, 'adjustStock']);
            Route::get('/{warehouseId}/movements',          [WarehouseController::class, 'movements']);
            Route::get('/{warehouseId}/barcodes',           [WarehouseController::class, 'barcodes']);
        });

        Route::prefix('warehouse/batches')->group(function () {
            Route::get('/floor-view',          [WarehouseBatchController::class, 'floorView']);
            Route::get('/product/{productId}', [WarehouseBatchController::class, 'byProduct']);
            Route::post('/',                   [WarehouseBatchController::class, 'receive']);
            Route::get('/{id}/logs',           [WarehouseBatchController::class, 'logs']);
            Route::post('/{id}/cull',          [WarehouseBatchController::class, 'cull']);
            Route::patch('/{id}/condition',    [WarehouseBatchController::class, 'updateCondition']);
            Route::patch('/{id}/transfer',     [WarehouseBatchController::class, 'transfer']);
        });

        Route::prefix('warehouse/locations')->group(function () {
            Route::get('/',              [WarehouseLocationController::class, 'index']);
            Route::post('/',             [WarehouseLocationController::class, 'store']);
            Route::get('/{id}',          [WarehouseLocationController::class, 'show']);
            Route::patch('/{id}',        [WarehouseLocationController::class, 'update']);
            Route::delete('/{id}',       [WarehouseLocationController::class, 'destroy']);
            Route::patch('/{id}/toggle', [WarehouseLocationController::class, 'toggle']);
        });

        Route::prefix('orders')->group(function () {
            Route::get('/',                                 [OrderController::class, 'index']);
            Route::post('/',                                [OrderController::class, 'store']);
            Route::get('/funding-requests/approved',        [OrderController::class, 'approvedFundingRequests']);
            Route::post('/from-funding/{fundingRequestId}', [OrderController::class, 'createFromFunding']);
            Route::get('/{id}',                             [OrderController::class, 'show']);
            Route::put('/{id}',                             [OrderController::class, 'update']);
            Route::patch('/{id}',                           [OrderController::class, 'update']);
            Route::delete('/{id}',                          [OrderController::class, 'destroy']);
            Route::patch('/{id}/status',                    [OrderController::class, 'updateStatus']);
            Route::post('/{id}/items',                      [OrderController::class, 'attachItems']);
            Route::delete('/{id}/items/{itemId}',           [OrderController::class, 'removeItem']);
            Route::post('/{id}/recalculate',                [OrderController::class, 'recalculateTotals']);
        });

        Route::prefix('shipments')->group(function () {
            Route::get('/',                [ShipmentController::class, 'index']);
            Route::post('/',               [ShipmentController::class, 'store']);
            Route::get('/{id}',            [ShipmentController::class, 'show']);
            Route::put('/{id}',            [ShipmentController::class, 'update']);
            Route::patch('/{id}',          [ShipmentController::class, 'update']);
            Route::delete('/{id}',         [ShipmentController::class, 'destroy']);
            Route::patch('/{id}/status',   [ShipmentController::class, 'updateStatus']);
            Route::patch('/{id}/tracking', [ShipmentController::class, 'updateTracking']);
            Route::patch('/{id}/ship',     [ShipmentController::class, 'markShipped']);
            Route::patch('/{id}/receive',  [ShipmentController::class, 'markReceived']);
        });

        Route::prefix('barcode')->group(function () {
            Route::post('/scan',     [BarcodeController::class, 'scan']);
            Route::post('/generate', [BarcodeController::class, 'generate']);
            Route::get('/lookup',    [BarcodeController::class, 'lookup']);
        });

        Route::prefix('analytics')->group(function () {
            Route::get('/summary',              [AnalyticsController::class, 'summary']);
            Route::get('/inventory',            [AnalyticsController::class, 'inventory']);
            Route::get('/orders',               [AnalyticsController::class, 'orders']);
            Route::get('/shipments',            [AnalyticsController::class, 'shipments']);
            Route::get('/supplier-performance', [AnalyticsController::class, 'supplierPerformance']);
            Route::get('/movements',            [AnalyticsController::class, 'movements']);
        });
    });

    // ----------------------------------------------------------
    // 6k. Finance
    // ----------------------------------------------------------

    Route::prefix('finance')->group(function () {
        Route::get('funding-requests',               [AccountingFundingRequestController::class, 'index']);
        Route::post('funding-requests/{id}/approve', [AccountingFundingRequestController::class, 'approve']);
        Route::post('funding-requests/{id}/reject',  [AccountingFundingRequestController::class, 'reject']);
    });

}); // end auth:sanctum

// ============================================================
// 7. ADMIN (add 'role:admin' middleware before production)
// ============================================================

Route::prefix('admin')->group(function () {

    // ── Vendor applications ───────────────────────────────────────────────
    Route::get('/vendor-applications',             [VendorApplicationController::class, 'index']);
    Route::get('/vendor-applications/statistics',  [VendorApplicationController::class, 'statistics']);
    Route::put('/vendor-applications/{id}/status', [VendorApplicationController::class, 'updateStatus']);
    Route::get('/vendor-applications/export',      [VendorApplicationController::class, 'export']);
    Route::get('/vendor-applications/{id}',        [VendorApplicationController::class, 'show']);
    Route::post('/vendor-applications/{id}/approve', [VendorApplicationController::class, 'approve']);
    Route::post('/vendor-applications/{id}/reject',  [VendorApplicationController::class, 'reject']);
    Route::get('/test',                            [VendorApplicationController::class, 'test']);
    Route::get('/reports',                   [ProductReportController::class, 'index']);   // ← NEW
    Route::post('/reports/{id}/review',      [ProductReportController::class, 'review']); // ← NEW

    // ── Admin product management ──────────────────────────────────────────
    Route::get('/products',                    [ProductController::class, 'index']);
    Route::get('/products/statistics',         [ProductController::class, 'statistics']);
    Route::post('/products/{id}/toggle-status',[ProductController::class, 'toggleStatus']);
    Route::delete('/products/{id}',            [ProductController::class, 'destroy']);

}); // end admin prefix

// ============================================================
// 8. FILE SERVING — static storage assets
// ============================================================

Route::options('/storage/{path}', fn () => response('', 204))
    ->where('path', '.*');

Route::get('/storage/{path}', function (string $path) {
    $fullPath    = storage_path('app/public/' . $path);
    $realPath    = realpath($fullPath);
    $storageBase = realpath(storage_path('app/public'));

    abort_unless($realPath && str_starts_with($realPath, $storageBase), 403);
    abort_unless(file_exists($realPath), 404);

    return response()->file($realPath, ['Cache-Control' => 'public, max-age=3600']);
})
->where('path', '.*')
->name('storage.file');

Route::get('/debug-cloudinary', function () {
    try {
        $cloudinaryUrl = env('CLOUDINARY_URL', 'NOT SET');
        
        return response()->json([
            'UNIQUE_VERSION_ID'     => 'FIX_V4_NESTED_CLOUD',
            'cloudinary_url_set'    => !empty($cloudinaryUrl),
            'has_duplicate_prefix'  => str_contains($cloudinaryUrl, 'CLOUDINARY_URL='),
            'first_20_chars'        => substr($cloudinaryUrl, 0, 20),
            'config_cloud_url'      => substr(config('cloudinary.cloud_url') ?? 'null', 0, 20),
            'has_cloud_key'         => config()->has('cloudinary.cloud'),
            'cloud_name_set'        => !empty(config('cloudinary.cloud.cloud_name')),
            'php_version'           => PHP_VERSION,
        ]);

    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});