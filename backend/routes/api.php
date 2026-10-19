<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Customer\CustomerProductController;
use App\Http\Controllers\Admin\VendorApplicationController;
use App\Http\Controllers\Admin\LoginAuditLogController;
use App\Http\Controllers\Admin\SubscriptionRevenueController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminVendorController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminCustomerController;
use App\Http\Controllers\Admin\AdminContentController;
use App\Http\Controllers\SiteContentController;
use App\Http\Controllers\Admin\AdminSupportController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\Admin\AdminReportsController;
use App\Http\Controllers\Admin\AdminReviewController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\ProductTaxonomyController;
use App\Http\Controllers\Admin\FinanceController as AdminFinanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\VendorApplicationResubmissionController;
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
use App\Http\Controllers\SupplyChainAnalyticsController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DeliveryBarcodeController;
use App\Http\Controllers\VendorStoreFrontController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\CustomerOrderTrackingController;
use App\Http\Controllers\OrderRequestController;
use App\Http\Controllers\CustomerReturnCaseController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\VendorBooksController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\VendorCrmController;
use App\Http\Controllers\CustomerInquiryController;
use App\Http\Controllers\VendorCatalogController;
use App\Http\Controllers\VendorDashboardController;
use App\Http\Controllers\VendorFulfillmentController;
use App\Http\Controllers\VendorPromotionController;
use App\Http\Controllers\VendorFinanceDashboardController;
use App\Http\Controllers\ProductReportController; // ← NEW
use App\Http\Controllers\VendorSubscriptionController;
use App\Http\Controllers\VendorAccountController;
use App\Http\Controllers\VendorCustomizationOptionController;
use App\Http\Controllers\VendorBouquetPresetController;

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
    Route::post('/two-factor/verify-passkey', [AuthController::class, 'verifyTwoFactorPasskey']);
    Route::post('/two-factor/send-otp', [AuthController::class, 'sendTwoFactorLoginOtp']);
    Route::post('/two-factor/verify-otp', [AuthController::class, 'verifyTwoFactorLoginOtp']);
    Route::post('/vendor/login', [AuthController::class, 'vendorLogin']);
});

Route::post('/auth/employee-login', [EmployeeAuthController::class, 'login']);

// ============================================================
// 2. PUBLIC — VENDOR REGISTRATION
// ============================================================

Route::post('/vendor/register', [VendorController::class, 'register']);
Route::get('/vendor/status',    [VendorController::class, 'checkStatus']);
Route::get('/vendor/resubmission/{token}', [VendorApplicationResubmissionController::class, 'showForVendor']);
Route::post('/vendor/resubmission/{token}', [VendorApplicationResubmissionController::class, 'submitForVendor']);

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
    Route::get('/',                    [VendorStoreFrontController::class, 'index']);
    Route::get('/{id}',                [VendorStoreFrontController::class, 'show']);
    Route::get('/{vendorId}/products', [VendorStoreFrontController::class, 'getProducts']);
});

Route::get('/stores/{storeId}/customizable-flowers', [VendorStoreFrontController::class, 'getCustomizableFlowers']);
Route::get('/stores/{storeId}/customization-options', [VendorStoreFrontController::class, 'getCustomizationOptions']);

// Website content for the shop and landing page (managed by admins)
Route::prefix('site')->group(function () {
    Route::get('/home',            [SiteContentController::class, 'home']);
    Route::get('/settings',        [SiteContentController::class, 'settings']);
    Route::get('/status',          [SiteContentController::class, 'status']);
    Route::get('/articles',        [SiteContentController::class, 'articles']);
    Route::get('/articles/{slug}', [SiteContentController::class, 'article']);
});

// Choices for the vendor product form (managed by admins)
Route::get('/product-taxonomies', [ProductTaxonomyController::class, 'index']);

// ── PUBLIC — Product reviews (no auth required) ───────────────────────────
Route::get('/products/{productId}/reviews', [ProductReviewController::class, 'index'])
    ->whereNumber('productId');
// Answered questions about a product (Customer relationships → Inquiries)
Route::get('/products/{productId}/questions', [CustomerInquiryController::class, 'productQuestions'])
    ->whereNumber('productId');

// ============================================================
// 4. PUBLIC — PAYMONGO WEBHOOK
// ============================================================

Route::post('/paymongo/webhook', [CheckoutController::class, 'handleWebhook'])
    ->middleware('paymongo.webhook');
Route::get('/payment/callback',  [CheckoutController::class, 'paymentCallback']);
Route::get('/subscription/payment/callback', [VendorSubscriptionController::class, 'paymentCallback']);

// ============================================================
// 5. PUBLIC — EMPLOYEE LEAVE (QR token, no auth)
// ============================================================

Route::prefix('public/leave')->group(function () {
    Route::post('/verify-qr', [EmployeeLeaveController::class, 'verifyQRToken']);
    Route::post('/submit',    [EmployeeLeaveController::class, 'submitLeaveRequest']);
});

Route::middleware('token.auth')->group(function () {

    // Read-only company subscription data for the shared route guard. It is
    // intentionally outside module protection so expired vendors can be
    // informed safely without granting access to a business module.
    Route::get('/subscription/access', [VendorSubscriptionController::class, 'access']);

    // Subscription billing is not implemented here. This sole Stage 2 endpoint
    // records the one-time Business trial for the authenticated vendor owner.
    Route::post('/vendor/subscription/business-trial', [VendorSubscriptionController::class, 'startBusinessTrial'])
        ->middleware('vendor');
    Route::post('/vendor/subscription/checkout', [VendorSubscriptionController::class, 'createCheckout'])
        ->middleware('vendor');
    Route::post('/vendor/subscription/checkout/{checkout}/cancel', [VendorSubscriptionController::class, 'cancelCheckout'])
        ->middleware('vendor');
    Route::get('/vendor/subscription/checkout/{checkout}', [VendorSubscriptionController::class, 'checkoutStatus'])
        ->middleware('vendor');

    // Notifications inbox and support requests (vendors and customers)
    Route::middleware('token.auth:user')->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->whereNumber('id');

        Route::get('/support/tickets', [SupportController::class, 'index']);
        Route::post('/support/tickets', [SupportController::class, 'store']);
        Route::get('/support/tickets/{id}', [SupportController::class, 'show'])->whereNumber('id');
        Route::post('/support/tickets/{id}/messages', [SupportController::class, 'reply'])->whereNumber('id');
        Route::post('/support/tickets/{id}/resolve', [SupportController::class, 'resolve'])->whereNumber('id');
    });

    // ----------------------------------------------------------
    // 6a. Auth — session management
    // ----------------------------------------------------------

    Route::middleware('token.auth:user')->group(function () {
        Route::get('/auth/me',      [AuthController::class, 'me']);
        Route::get('/auth/session', [AuthController::class, 'session']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
    });

    Route::middleware('token.auth:employee')->group(function () {
        Route::get('/auth/employee-me',      [EmployeeAuthController::class, 'me']);
        Route::post('/auth/employee-logout', [EmployeeAuthController::class, 'logout']);
    });

    // ----------------------------------------------------------
    // 6b. Profile
    // ----------------------------------------------------------

    Route::prefix('profile')->group(function () {
        Route::get('/user-profile', [ProfileController::class, 'getProfile']);
        Route::put('/user-details', [ProfileController::class, 'updateProfile']);
        Route::post('/picture',     [ProfileController::class, 'updateProfilePicture']);
        Route::post('/two-factor/manage/start', [ProfileController::class, 'startTwoFactorManagement']);
        Route::post('/two-factor/manage/verify-email-otp', [ProfileController::class, 'verifyTwoFactorManagementEmailOtp']);
        Route::post('/two-factor/manage/passkey', [ProfileController::class, 'setTwoFactorPasskey']);
    });

    // ----------------------------------------------------------
    // 6c. Checkout & Cart
    // ----------------------------------------------------------

    Route::prefix('checkout')->group(function () {
        Route::post('/initialize',      [CheckoutController::class, 'initializeCheckout']);
        Route::post('/create-order',    [CheckoutController::class, 'createOrder']);
        Route::get('/orders/{orderId}', [CheckoutController::class, 'getOrder']);
        Route::post('/orders/{orderId}/abandon-payment', [CheckoutController::class, 'abandonPayment'])
            ->whereNumber('orderId');
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

        // ── Questions to a shop about a product or an order ───────────────────
        Route::get('/inquiries',  [CustomerInquiryController::class, 'mine']);
        Route::post('/inquiries', [CustomerInquiryController::class, 'store'])->middleware('throttle:10,60');

        // ── Order tracking ────────────────────────────────────────────────────
        Route::get('/orders',      [CustomerOrderTrackingController::class, 'index']);
        Route::get('/orders/{id}', [CustomerOrderTrackingController::class, 'show'])->whereNumber('id');

        Route::post('/orders/{id}/complete',         [CustomerOrderTrackingController::class, 'complete'])->whereNumber('id');
        Route::post('/orders/{id}/confirm-received', [CustomerOrderTrackingController::class, 'confirmReceived'])->whereNumber('id');
        Route::post('/orders/{id}/cancel',           [CustomerOrderTrackingController::class, 'cancel'])->whereNumber('id');
        Route::post('/orders/{id}/request-return',   [CustomerOrderTrackingController::class, 'requestReturn'])->whereNumber('id');
        Route::post('/orders/{id}/request-refund',   [CustomerOrderTrackingController::class, 'requestRefund'])->whereNumber('id');

        // ── Return/refund cases the customer and the shop settle together ──
        Route::post('/orders/{id}/return-case', [CustomerOrderTrackingController::class, 'openReturnCase'])->whereNumber('id');
        Route::get('/return-cases/{id}', [CustomerReturnCaseController::class, 'show'])->whereNumber('id');
        Route::post('/return-cases/{id}/{action}', [CustomerReturnCaseController::class, 'act'])
            ->whereNumber('id')
            ->whereIn('action', ['accept', 'offer', 'withdraw', 'message', 'confirm-refund', 'refund-missing', 'confirm-replacement', 'replacement-missing']);
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

    // ── Stores near the customer's pinned location ────────────────────────
    Route::get('/stores/nearby', [VendorStoreFrontController::class, 'nearby']);

    // ── Vendor follow/unfollow ────────────────────────────────────────────
    Route::post('/vendors/{vendorId}/follow',   [VendorStoreFrontController::class, 'follow']);
    Route::post('/vendors/{vendorId}/unfollow', [VendorStoreFrontController::class, 'unfollow']);

    // ── Chat ─────────────────────────────────────────────────────────────
    Route::prefix('chat')->group(function () {
        Route::middleware('employee.module:crm,view')->group(function () {
            Route::get('/conversations',                  [ChatController::class, 'getConversations']);
            Route::get('/my-conversations',               [ChatController::class, 'getConversations']);
            Route::get('/conversations/{id}/messages',    [ChatController::class, 'getMessages']);
            Route::get('/poll',                           [ChatController::class, 'pollNewMessages']);
            Route::get('/customer/{id}/details',          [ChatController::class, 'getUserDetails']);
            Route::get('/vendor/{id}/details',            [ChatController::class, 'getUserDetails']);
        });

        Route::middleware('employee.module:crm,create')->group(function () {
            Route::post('/messages/send',                 [ChatController::class, 'sendMessage']);
            Route::post('/conversations/start',           [ChatController::class, 'startConversation']);
            Route::get('/search-users',                   [ChatController::class, 'searchUsers']);
            Route::get('/search-customers',               [ChatController::class, 'searchUsers']);
            Route::get('/search-vendors',                 [ChatController::class, 'searchUsers']);
        });

        Route::middleware('employee.module:crm,edit')->group(function () {
            Route::post('/conversations/{id}/archive',    [ChatController::class, 'archiveConversation'])->whereNumber('id');
            Route::post('/conversations/{id}/unarchive',  [ChatController::class, 'unarchiveConversation'])->whereNumber('id');
        });

        Route::middleware('employee.module:crm,delete')->group(function () {
            Route::delete('/conversations/{id}',          [ChatController::class, 'deleteConversation'])->whereNumber('id');
        });
    });

    // ----------------------------------------------------------
    // 6e. Vendor portal
    // ----------------------------------------------------------

    // These are owner-facing administration and vendor-portal endpoints.  An
    // employee must use the ERP endpoints below, where employee.module
    // enforces both the company subscription and their delegated permission.
    // Profile/password endpoints deliberately have no subscription middleware.
    Route::prefix('vendor')->middleware('vendor')->group(function () {

        Route::get('/dashboard', fn () => response()->json(['message' => 'Vendor dashboard']));
        // Home page overview; open to every plan, sections follow the plan's modules
        Route::get('/dashboard/overview', [VendorDashboardController::class, 'overview']);

        Route::prefix('employees')->group(function () {
            Route::get('/',              [EmployeeController::class, 'index']);
            Route::get('/statistics',    [EmployeeController::class, 'statistics']);
            Route::get('/search',        [EmployeeController::class, 'search']);
            Route::post('/',             [EmployeeController::class, 'store'])->middleware('resource.limit:staff_employees');
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
            Route::post('/store-cover',     [VendorProfileController::class, 'updateStoreCover']);
            Route::delete('/store-cover',   [VendorProfileController::class, 'deleteStoreCover']);
            Route::put('/change-password',  [VendorProfileController::class, 'changePassword']);
        });

        Route::middleware('subscription.module:products')->group(function () {
            Route::get('/products',            [ProductController::class, 'myProducts']);
            Route::get('/my-products',         [ProductController::class, 'myProducts']);
            Route::get('/products/draft',      [ProductController::class, 'draftProducts']);
            Route::get('/draft-products',      [ProductController::class, 'draftProducts']);
            Route::get('/products/inactive',   [ProductController::class, 'inactiveProducts']);
            Route::get('/inactive-products',   [ProductController::class, 'inactiveProducts']);
            Route::post('/products',           [ProductController::class, 'store'])->middleware('resource.limit:products');
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

            // 3D customizer wrappers, fillers and ribbons.
            Route::get('/customization-options',               [VendorCustomizationOptionController::class, 'index']);
            // Occasion presets (Wedding, Birthday, …) and their sizes for the customizer
            Route::get('/bouquet-presets',                     [VendorBouquetPresetController::class, 'index']);
            Route::put('/bouquet-presets',                     [VendorBouquetPresetController::class, 'update']);
            Route::post('/customization-options',              [VendorCustomizationOptionController::class, 'store']);
            Route::post('/customization-options/{id}',         [VendorCustomizationOptionController::class, 'update'])->whereNumber('id');
            Route::patch('/customization-options/{id}/active', [VendorCustomizationOptionController::class, 'setActive'])->whereNumber('id');
            Route::delete('/customization-options/{id}',       [VendorCustomizationOptionController::class, 'destroy'])->whereNumber('id');

            // Shop & product management: products, inventory, categories, own sale prices
            Route::prefix('catalog')->group(function () {
                Route::get('/products',                    [VendorCatalogController::class, 'products']);
                Route::post('/products/bulk',              [VendorCatalogController::class, 'bulk']);
                Route::post('/products/{id}/stock',        [VendorCatalogController::class, 'adjustStock'])->whereNumber('id');
                Route::patch('/products/{id}/stock-levels', [VendorCatalogController::class, 'stockLevels'])->whereNumber('id');
                Route::patch('/products/{id}/discount',    [VendorCatalogController::class, 'discount'])->whereNumber('id');
                Route::get('/stock-history',               [VendorCatalogController::class, 'stockHistory']);
            });

            // Seasonal offers
            Route::get('/promotions',               [VendorPromotionController::class, 'index']);
            Route::post('/promotions',              [VendorPromotionController::class, 'store']);
            Route::put('/promotions/{id}',          [VendorPromotionController::class, 'update'])->whereNumber('id');
            Route::post('/promotions/{id}/toggle',  [VendorPromotionController::class, 'toggle'])->whereNumber('id');
            Route::delete('/promotions/{id}',       [VendorPromotionController::class, 'destroy'])->whereNumber('id');
        });

        // Subscription & account (every plan, even an expired one, so the vendor can renew)
        Route::prefix('account')->group(function () {
            Route::get('/subscription',            [VendorAccountController::class, 'subscription']);
            Route::get('/billing',                 [VendorAccountController::class, 'billing']);
            Route::get('/billing/{id}/invoice',    [VendorAccountController::class, 'invoice'])->whereNumber('id');
            Route::get('/usage',                   [VendorAccountController::class, 'usage']);
            Route::get('/shop-settings',           [VendorAccountController::class, 'shopSettings']);
            Route::put('/shop-settings',           [VendorAccountController::class, 'updateShopSettings']);
            Route::get('/security',                [VendorAccountController::class, 'security']);
            Route::post('/security/sign-out-others', [VendorAccountController::class, 'signOutOtherDevices']);
        });

        // My Shop: profile, location and business hours (every plan)
        Route::get('/shop', [VendorProfileController::class, 'getShop']);
        Route::put('/shop', [VendorProfileController::class, 'updateShop']);

        Route::middleware('subscription.module:orders')->group(function () {
            Route::get('/orders',                  [VendorOrdersController::class, 'getAllOrders']);
            Route::get('/orders/statistics',       [VendorOrdersController::class, 'getOrderStatistics']);
            Route::get('/orders/{orderId}',        [VendorOrdersController::class, 'getOrderDetails']);
            Route::put('/orders/{orderId}/status', [VendorOrdersController::class, 'updateOrderStatus']);
        });

        // Orders & Fulfillment pages
        Route::prefix('fulfillment')->middleware('subscription.module:orders')->group(function () {
            Route::get('/orders',      [VendorFulfillmentController::class, 'orders']);
            Route::get('/orders/{id}', [VendorFulfillmentController::class, 'show'])->whereNumber('id');
            Route::get('/schedule',    [VendorFulfillmentController::class, 'schedule']);
        });
        Route::get('/fulfillment/calendar', [VendorFulfillmentController::class, 'calendar'])->middleware('subscription.module:calendar');

        Route::middleware('subscription.module:calendar')->group(function () {
            Route::get('/orders/calendar-data', [VendorOrdersController::class, 'getCalendarData']);
            Route::get('/orders/for-date', [VendorOrdersController::class, 'getOrdersForDate']);
        });

        Route::prefix('reservations')->middleware('subscription.module:calendar')->group(function () {
            Route::get('/calendar',             [VendorOrdersController::class, 'getCalendarData']);
            Route::get('/closed-dates',         [VendorOrdersController::class, 'getClosedDates']);
            Route::post('/close-date',          [VendorOrdersController::class, 'markDateAsClosed']);
            Route::delete('/close-date/{id}',   [VendorOrdersController::class, 'removeClosedDate']);
            Route::get('/orders-for-date',      [VendorOrdersController::class, 'getOrdersForDate']);
            Route::get('/orders/calendar-data', [VendorOrdersController::class, 'getCalendarData']);
        });

        Route::post('/orders/{orderId}/scan-delivery', [DeliveryController::class, 'scan'])->middleware('subscription.module:scanning');
        Route::get('/deliveries', [DeliveryController::class, 'vendorOrders'])->middleware('subscription.module:deliveries');

        // Finance & business management pages
        Route::prefix('books')->middleware('subscription.module:finance')->group(function () {
            Route::get('/transactions',          [VendorBooksController::class, 'transactions']);
            Route::get('/expenses',              [VendorBooksController::class, 'expenses']);
            Route::post('/expenses',             [VendorBooksController::class, 'storeExpense']);
            Route::post('/expenses/{id}',        [VendorBooksController::class, 'updateExpense'])->whereNumber('id');
            Route::delete('/expenses/{id}',      [VendorBooksController::class, 'destroyExpense'])->whereNumber('id');
            Route::get('/profit-loss',           [VendorBooksController::class, 'profitLoss']);
            Route::get('/reports',               [VendorBooksController::class, 'reports']);
            Route::get('/reports/export',        [VendorBooksController::class, 'exportReport']);
            Route::get('/refunds',               [VendorBooksController::class, 'refunds']);
        });

        // Customer relationships pages (chat itself uses the /chat routes)
        Route::prefix('crm')->group(function () {
            Route::middleware('subscription.module:crm')->group(function () {
                Route::get('/inquiries',                [VendorCrmController::class, 'inquiries']);
                Route::post('/inquiries/{id}/answer',   [VendorCrmController::class, 'answer'])->whereNumber('id');
                Route::post('/inquiries/{id}/status',   [VendorCrmController::class, 'inquiryStatus'])->whereNumber('id');
                Route::get('/customers/{id}',           [VendorCrmController::class, 'customer'])->whereNumber('id');
            });
            Route::middleware('subscription.module:products')->group(function () {
                Route::get('/reviews',                  [VendorCrmController::class, 'reviews']);
                Route::post('/reviews/{id}/reply',      [VendorCrmController::class, 'reply'])->whereNumber('id');
            });
            Route::get('/notifications',                [VendorCrmController::class, 'notifications']);
            Route::put('/notifications/preferences',    [VendorCrmController::class, 'savePreferences']);
        });

        // Staff & operations pages (shop owners; employees use the ERP)
        Route::prefix('staff')->group(function () {
            Route::middleware('subscription.module:staff')->group(function () {
                Route::get('/roles',               [StaffController::class, 'roles']);
                Route::post('/roles',              [StaffController::class, 'storeRole']);
                Route::put('/roles/{id}',          [StaffController::class, 'updateRole'])->whereNumber('id');
                Route::delete('/roles/{id}',       [StaffController::class, 'destroyRole'])->whereNumber('id');
                Route::get('/employees',           [StaffController::class, 'employees']);
                Route::post('/employees',          [StaffController::class, 'storeEmployee'])->middleware('resource.limit:staff_employees');
                Route::put('/employees/{id}',      [StaffController::class, 'updateEmployee'])->whereNumber('id');
                Route::get('/schedules',           [StaffController::class, 'schedules']);
                Route::post('/shifts',             [StaffController::class, 'saveShift']);
                Route::post('/shifts/copy-week',   [StaffController::class, 'copyWeek']);
                Route::delete('/shifts/{id}',      [StaffController::class, 'destroyShift'])->whereNumber('id');
                Route::get('/activity',            [StaffController::class, 'activity']);
            });
            Route::middleware('subscription.module:attendance')->group(function () {
                Route::get('/attendance',          [StaffController::class, 'attendance']);
                Route::post('/attendance',         [StaffController::class, 'storeAttendance']);
                Route::put('/attendance/{id}',     [StaffController::class, 'updateAttendance'])->whereNumber('id');
            });
            Route::middleware('subscription.module:payroll')->group(function () {
                Route::get('/payroll',             [StaffController::class, 'payroll']);
                Route::post('/payroll/preview',    [StaffController::class, 'previewPayroll']);
                Route::post('/payroll',            [StaffController::class, 'createPayroll']);
                Route::post('/payroll/{action}',   [StaffController::class, 'payrollAction'])->whereIn('action', ['approve', 'reject', 'paid']);
            });
        });

        Route::prefix('finance')->middleware('subscription.module:finance')->group(function () {
            Route::get('/overview',     [VendorFinanceDashboardController::class, 'overview']);
            Route::get('/transactions', [VendorFinanceDashboardController::class, 'transactions']);
            Route::get('/cashflow',     [VendorFinanceDashboardController::class, 'cashflow']);
        });
    });

    // ----------------------------------------------------------
    // 6f. SC Coordinator portal
    // ----------------------------------------------------------

    Route::prefix('sc')->group(function () {
        Route::get('/orders', [DeliveryController::class, 'scOrders'])->middleware('employee.module:deliveries,view');
        Route::post('/barcode/scan', [DeliveryController::class, 'scan'])->middleware('employee.module:order_scan,edit');
        Route::get('/orders/{orderId}/barcode', [DeliveryBarcodeController::class, 'show'])->middleware('employee.module:deliveries,view');
        Route::get('/order-requests', [OrderRequestController::class, 'index'])->middleware('employee.module:deliveries,view');
        Route::post('/order-requests/{id}/approve', [OrderRequestController::class, 'approve'])->middleware('employee.module:deliveries,approve');
        Route::post('/order-requests/{id}/reject', [OrderRequestController::class, 'reject'])->middleware('employee.module:deliveries,reject');
        Route::get('/order-requests/{id}', [OrderRequestController::class, 'show'])->whereNumber('id')->middleware('employee.module:deliveries,view');
        Route::post('/order-requests/{id}/{action}', [OrderRequestController::class, 'act'])
            ->whereNumber('id')
            ->whereIn('action', ['accept', 'offer', 'message', 'item-received', 'refund-sent', 'replacement-sent'])
            ->middleware('employee.module:deliveries,approve');
    });

    // ----------------------------------------------------------
    // 6g. Delivery management
    // ----------------------------------------------------------

    Route::prefix('deliveries')->group(function () {
        Route::patch('/{id}/status', [DeliveryController::class, 'updateStatus'])->middleware('employee.module:deliveries,edit');
        Route::get('/{id}/logs',     [DeliveryController::class, 'logs'])->middleware('employee.module:deliveries,view');
    });

    // ----------------------------------------------------------
    // 6h. HR — Employee info, attendance, QR, payroll, leave
    // ----------------------------------------------------------

    Route::prefix('employees-info')->group(function () {
        Route::middleware('employee.module:employees,view')->group(function () {
            Route::get('/',              [EmployeeInfoController::class, 'index']);
            Route::get('/statistics',    [EmployeeInfoController::class, 'statistics']);
            Route::get('/{id}',          [EmployeeInfoController::class, 'show']);
        });

        Route::post('/', [EmployeeInfoController::class, 'store'])->middleware('employee.module:employees,create');
        Route::put('/{id}', [EmployeeInfoController::class, 'update'])->middleware('employee.module:employees,edit');
        Route::patch('/{id}/status', [EmployeeInfoController::class, 'updateStatus'])->middleware('employee.module:employees,edit');
        Route::delete('/{id}', [EmployeeInfoController::class, 'destroy'])->middleware('employee.module:employees,delete');
    });

    Route::prefix('attendance')->group(function () {
        Route::middleware('employee.module:attendance,view')->group(function () {
            Route::get('/',        [AttendanceController::class, 'index']);
            Route::get('/summary', [AttendanceController::class, 'summary']);
        });

        Route::middleware('employee.module:attendance,create')->group(function () {
            Route::post('/scan',     [AttendanceController::class, 'scanQR']);
            Route::post('/',         [AttendanceController::class, 'store']);
            Route::post('/time-in',  [AttendanceController::class, 'timeIn']);
            Route::post('/time-out', [AttendanceController::class, 'timeOut']);
        });
        Route::put('/{id}', [AttendanceController::class, 'update'])->middleware('employee.module:attendance,edit');
        Route::delete('/{id}', [AttendanceController::class, 'destroy'])->middleware('employee.module:attendance,delete');
    });

        Route::prefix('employees/{employeeId}/qr-code')->group(function () {
            Route::middleware('employee.module:employees,view')->group(function () {
                Route::get('/svg',       [EmployeeQRController::class, 'getSVG']);
                Route::get('/base64',    [EmployeeQRController::class, 'getBase64']);
            });

            Route::get('/download', [EmployeeQRController::class, 'download'])
                ->middleware('employee.module:employees,export');

        Route::middleware('employee.module:employees,create')->group(function () {
            Route::post('/generate', [EmployeeQRController::class, 'generate']);
        });
    });

    Route::prefix('payroll')->group(function () {
        Route::middleware('employee.module:payroll_requests,view')->group(function () {
            Route::get('/finance-requests', [PayrollController::class, 'financeRequests']);
            Route::get('/finance-summary',  [PayrollController::class, 'financeSummary']);
        });

        Route::post('/finance-approve', [PayrollController::class, 'financeApprove'])->middleware('employee.module:payroll_requests,approve');
        Route::post('/finance-reject', [PayrollController::class, 'financeReject'])->middleware('employee.module:payroll_requests,reject');
        Route::post('/mark-paid', [PayrollController::class, 'markAsPaid'])->middleware('employee.module:payroll_requests,edit');

        Route::middleware('employee.module:payroll,view')->group(function () {
            Route::get('/',                 [PayrollController::class, 'index']);
            Route::get('/summary',          [PayrollController::class, 'summary']);
            Route::get('/{id}',             [PayrollController::class, 'show'])->whereNumber('id');
        });

        Route::middleware('employee.module:payroll,create')->group(function () {
            Route::post('/',                [PayrollController::class, 'store']);
            Route::post('/preview',         [PayrollController::class, 'preview']);
        });
        Route::post('/hr-approve', [PayrollController::class, 'hrApprove'])->middleware('employee.module:payroll,approve');
        Route::delete('/{id}', [PayrollController::class, 'destroy'])->whereNumber('id')->middleware('employee.module:payroll,delete');
    });

    Route::prefix('leaves')->group(function () {
        Route::middleware('employee.module:leave_management,view')->group(function () {
            Route::get('/',            [EmployeeLeaveController::class, 'index']);
            Route::get('/statistics',  [EmployeeLeaveController::class, 'getStatistics']);
        });

        Route::put('/{id}/status', [EmployeeLeaveController::class, 'updateStatus'])
            ->middleware('employee.leave-review');
        Route::delete('/{id}', [EmployeeLeaveController::class, 'destroy'])->middleware('employee.module:leave_management,delete');
    });

    // ----------------------------------------------------------
    // 6i. Procurement — Inventory Manager
    // ----------------------------------------------------------

    // Inventory procurement is distinct from the vendor product catalog. The
    // nested employee checks still enforce each employee's RBAC permission.
    Route::prefix('procurement/inventory')->middleware('subscription.module:procurement')->group(function () {
        Route::middleware('employee.module:inventory_products,view')->group(function () {
            Route::get('/products',          [ProductController::class, 'myProducts']);
            Route::get('/my-products',       [ProductController::class, 'myProducts']);
            Route::get('/products/draft',    [ProductController::class, 'draftProducts']);
            Route::get('/draft-products',    [ProductController::class, 'draftProducts']);
            Route::get('/products/inactive', [ProductController::class, 'inactiveProducts']);
            Route::get('/inactive-products', [ProductController::class, 'inactiveProducts']);
            Route::get('/products/search',   [ProductController::class, 'searchForWarehouse']);
            Route::get('/products/{id}',     [ProductController::class, 'show']);
        });

        Route::post('/products', [ProductController::class, 'store'])->middleware(['employee.module:inventory_products,create', 'resource.limit:products']);
        Route::middleware('employee.module:inventory_products,edit')->group(function () {
            Route::put('/products/{id}',     [ProductController::class, 'update']);
            Route::patch('/products/{id}',   [ProductController::class, 'update']);
            Route::post('/products/{id}/toggle-status',             [ProductController::class, 'toggleStatus']);
            Route::post('/products/{id}/status',                    [ProductController::class, 'updateStatus']);
            Route::patch('/products/{id}/stock',                    [ProductController::class, 'updateStock']);
            Route::post('/products/{id}/update-stock',              [ProductController::class, 'updateStock']);
        });
        Route::delete('/products/{id}', [ProductController::class, 'destroy'])->middleware('employee.module:inventory_products,delete');
        Route::delete('/products/{productId}/images/{imageId}', [ProductController::class, 'deleteImage'])->middleware('employee.module:inventory_products,delete');
        Route::delete('/products/{productId}/model', [ProductController::class, 'deleteModel'])->middleware('employee.module:inventory_products,delete');

        Route::middleware('employee.module:inventory_funding,view')->group(function () {
            Route::get('eligible-approvers',            [FundingRequestController::class, 'getEligibleApprovers']);
            Route::get('funding-requests/products',     [FundingRequestController::class, 'getProducts']);
            Route::get('funding-requests',              [FundingRequestController::class, 'index']);
            Route::get('funding-requests/{id}',         [FundingRequestController::class, 'show']);
        });

        Route::middleware('employee.module:inventory_funding,create')->group(function () {
            Route::post('funding-requests',             [FundingRequestController::class, 'store']);
            Route::post('funding-requests/{id}/submit', [FundingRequestController::class, 'submitToAccounting']);
        });
        Route::middleware('employee.module:inventory_funding,edit')->group(function () {
            Route::put('funding-requests/{id}',         [FundingRequestController::class, 'update']);
        });
        Route::delete('funding-requests/{id}', [FundingRequestController::class, 'destroy'])->middleware('employee.module:inventory_funding,delete');
    });

    // ----------------------------------------------------------
    // 6j. Procurement — Supply Chain Coordinator
    // ----------------------------------------------------------

    Route::prefix('procurement/supply-chain')->group(function () {

        Route::prefix('suppliers')->group(function () {
            Route::middleware('employee.module:suppliers,view')->group(function () {
                Route::get('/',               [SupplierController::class, 'index']);
                Route::get('/{id}',           [SupplierController::class, 'show']);
                Route::get('/{supplierId}/contacts',         [SupplierController::class, 'contacts']);
            });

            Route::post('/', [SupplierController::class, 'store'])->middleware('employee.module:suppliers,create');
            Route::post('/{supplierId}/contacts', [SupplierController::class, 'storeContact'])->middleware('employee.module:suppliers,create');
            Route::middleware('employee.module:suppliers,edit')->group(function () {
                Route::put('/{id}',           [SupplierController::class, 'update']);
                Route::patch('/{id}',         [SupplierController::class, 'update']);
                Route::patch('/{id}/activate',   [SupplierController::class, 'activate']);
                Route::patch('/{id}/deactivate', [SupplierController::class, 'deactivate']);
                Route::patch('/{id}/blacklist',  [SupplierController::class, 'blacklist']);
                Route::put('/{supplierId}/contacts/{id}',    [SupplierController::class, 'updateContact']);
            });
            Route::delete('/{id}', [SupplierController::class, 'destroy'])->middleware('employee.module:suppliers,delete');
            Route::delete('/{supplierId}/contacts/{id}', [SupplierController::class, 'destroyContact'])->middleware('employee.module:suppliers,delete');
        });

        Route::prefix('warehouses')->group(function () {
            Route::middleware('employee.module:warehouse,view')->group(function () {
                Route::get('/',        [WarehouseController::class, 'index']);
                Route::get('/{id}',    [WarehouseController::class, 'show']);
                Route::get('/{warehouseId}/items',              [WarehouseController::class, 'items']);
                Route::get('/{warehouseId}/movements',          [WarehouseController::class, 'movements']);
                Route::get('/{warehouseId}/barcodes',           [WarehouseController::class, 'barcodes']);
            });

            Route::post('/', [WarehouseController::class, 'store'])
                ->middleware(['employee.module:warehouse,create', 'resource.limit:warehouses']);
            Route::post('/{warehouseId}/items', [WarehouseController::class, 'addItem'])->middleware('employee.module:warehouse,create');
            Route::middleware('employee.module:warehouse,edit')->group(function () {
                Route::put('/{id}',    [WarehouseController::class, 'update']);
                Route::patch('/{id}',  [WarehouseController::class, 'update']);
                Route::put('/{warehouseId}/items/{id}',         [WarehouseController::class, 'updateItem']);
                Route::patch('/{warehouseId}/items/{id}/stock', [WarehouseController::class, 'adjustStock']);
            });
            Route::delete('/{id}', [WarehouseController::class, 'destroy'])->middleware('employee.module:warehouse,delete');
        });

        Route::prefix('warehouse/batches')->group(function () {
            Route::middleware('employee.module:warehouse,view')->group(function () {
                Route::get('/floor-view',          [WarehouseBatchController::class, 'floorView']);
                Route::get('/product/{productId}', [WarehouseBatchController::class, 'byProduct']);
                Route::get('/{id}/logs',           [WarehouseBatchController::class, 'logs']);
            });

            Route::post('/', [WarehouseBatchController::class, 'receive'])->middleware('employee.module:warehouse,create');
            Route::middleware('employee.module:warehouse,edit')->group(function () {
                Route::post('/{id}/cull',          [WarehouseBatchController::class, 'cull']);
                Route::patch('/{id}/condition',    [WarehouseBatchController::class, 'updateCondition']);
                Route::patch('/{id}/transfer',     [WarehouseBatchController::class, 'transfer']);
            });
        });

        Route::prefix('warehouse/locations')->group(function () {
            Route::middleware('employee.module:warehouse,view')->group(function () {
                Route::get('/',              [WarehouseLocationController::class, 'index']);
                Route::get('/{id}',          [WarehouseLocationController::class, 'show']);
            });

            Route::post('/', [WarehouseLocationController::class, 'store'])->middleware('employee.module:warehouse,create');
            Route::middleware('employee.module:warehouse,edit')->group(function () {
                Route::patch('/{id}',        [WarehouseLocationController::class, 'update']);
                Route::patch('/{id}/toggle', [WarehouseLocationController::class, 'toggle']);
            });
            Route::delete('/{id}', [WarehouseLocationController::class, 'destroy'])->middleware('employee.module:warehouse,delete');
        });

        Route::prefix('orders')->group(function () {
            Route::middleware('employee.module:sc_orders,view')->group(function () {
                Route::get('/',                                 [OrderController::class, 'index']);
                Route::get('/funding-requests/approved',        [OrderController::class, 'approvedFundingRequests']);
                Route::get('/{id}',                             [OrderController::class, 'show']);
            });

            Route::middleware('employee.module:sc_orders,create')->group(function () {
                Route::post('/',                                [OrderController::class, 'store']);
                Route::post('/from-funding/{fundingRequestId}', [OrderController::class, 'createFromFunding']);
                Route::post('/{id}/items', [OrderController::class, 'attachItems']);
            });
            Route::middleware('employee.module:sc_orders,edit')->group(function () {
                Route::put('/{id}',                             [OrderController::class, 'update']);
                Route::patch('/{id}',                           [OrderController::class, 'update']);
                Route::patch('/{id}/status',                    [OrderController::class, 'updateStatus']);
                Route::post('/{id}/recalculate',                [OrderController::class, 'recalculateTotals']);
            });
            Route::delete('/{id}', [OrderController::class, 'destroy'])->middleware('employee.module:sc_orders,delete');
            Route::delete('/{id}/items/{itemId}', [OrderController::class, 'removeItem'])->middleware('employee.module:sc_orders,delete');
        });

        Route::prefix('shipments')->middleware('subscription.module:logistics')->group(function () {
            Route::middleware('employee.module:deliveries,view')->group(function () {
                Route::get('/',                [ShipmentController::class, 'index']);
                Route::get('/{id}',            [ShipmentController::class, 'show']);
            });

            Route::post('/', [ShipmentController::class, 'store'])->middleware('employee.module:deliveries,edit');
            Route::middleware('employee.module:deliveries,edit')->group(function () {
                Route::put('/{id}',            [ShipmentController::class, 'update']);
                Route::patch('/{id}',          [ShipmentController::class, 'update']);
                Route::patch('/{id}/status',   [ShipmentController::class, 'updateStatus']);
                Route::patch('/{id}/tracking', [ShipmentController::class, 'updateTracking']);
                Route::patch('/{id}/ship',     [ShipmentController::class, 'markShipped']);
                Route::patch('/{id}/receive',  [ShipmentController::class, 'markReceived']);
            });
            Route::delete('/{id}', [ShipmentController::class, 'destroy'])->middleware('employee.module:deliveries,edit');
        });

        Route::prefix('barcode')->group(function () {
            Route::middleware('employee.module:order_scan,view')->group(function () {
                Route::get('/lookup',    [BarcodeController::class, 'lookup']);
            });

            Route::middleware('employee.module:order_scan,edit')->group(function () {
                Route::post('/scan',     [BarcodeController::class, 'scan']);
                Route::post('/generate', [BarcodeController::class, 'generate']);
            });
        });

        Route::prefix('analytics')->group(function () {
            Route::middleware('employee.module:sc_dashboard,view')->group(function () {
                Route::get('/summary',              [SupplyChainAnalyticsController::class, 'summary']);
                Route::get('/inventory',            [SupplyChainAnalyticsController::class, 'inventory']);
                Route::get('/orders',               [AnalyticsController::class, 'orders']);
                Route::get('/shipments',            [AnalyticsController::class, 'shipments']);
                Route::get('/supplier-performance', [SupplyChainAnalyticsController::class, 'supplierPerformance']);
                Route::get('/movements',            [AnalyticsController::class, 'movements']);
            });
        });
    });

    // ----------------------------------------------------------
    // 6k. Finance
    // ----------------------------------------------------------

    Route::prefix('finance')->group(function () {
        Route::middleware('employee.module:funding_requests,view')->group(function () {
            Route::get('funding-requests',               [AccountingFundingRequestController::class, 'index']);
        });

        Route::post('funding-requests/{id}/approve', [AccountingFundingRequestController::class, 'approve'])->middleware('employee.module:funding_requests,approve');
        Route::post('funding-requests/{id}/reject', [AccountingFundingRequestController::class, 'reject'])->middleware('employee.module:funding_requests,reject');
    });

    // ── Admin panel ───────────────────────────────────────────────────────
    // Every route needs a permission from the admin's role (AdminRole::PERMISSIONS)
    // and every change is written to the audit log.
    Route::middleware(['role:admin', 'admin.audit'])->prefix('admin')->group(function () {
        Route::get('/security/me', [SecurityController::class, 'me']);

        Route::get('/login-logs', [LoginAuditLogController::class, 'index'])->middleware('admin.can:security.view');
        Route::get('/dashboard', [AdminDashboardController::class, 'overview'])->middleware('admin.can:dashboard.view');

        // Reports & analytics (CSV / PDF downloads are audited as exports)
        Route::get('/analytics', [AdminReportsController::class, 'index'])->middleware('admin.can:reports.view');
        Route::get('/analytics/export', [AdminReportsController::class, 'export'])->middleware('admin.can:reports.export');

        Route::middleware('admin.can:revenue.view')->prefix('subscriptions')->group(function () {
            Route::get('/revenue', [SubscriptionRevenueController::class, 'overview']);
            Route::get('/transactions', [SubscriptionRevenueController::class, 'transactions']);
            Route::get('/transactions/export', [SubscriptionRevenueController::class, 'export'])->middleware('admin.can:finance.export');
            Route::get('/subscribers', [SubscriptionRevenueController::class, 'subscribers']);
        });

        // Read-only oversight of vendor orders (plus private admin notes)
        Route::prefix('orders')->group(function () {
            Route::middleware('admin.can:orders.view')->group(function () {
                Route::get('/summary', [AdminOrderController::class, 'summary']);
                Route::get('/', [AdminOrderController::class, 'index']);
                Route::get('/{id}', [AdminOrderController::class, 'show'])->whereNumber('id');
            });
            Route::post('/{id}/notes', [AdminOrderController::class, 'addNote'])->whereNumber('id')->middleware('admin.can:orders.notes');
        });

        // Approved vendors: performance, products, orders, subscription, suspension
        Route::prefix('vendors')->group(function () {
            Route::middleware('admin.can:vendors.view')->group(function () {
                Route::get('/summary', [AdminVendorController::class, 'summary']);
                Route::get('/', [AdminVendorController::class, 'index']);
                Route::get('/{id}', [AdminVendorController::class, 'show'])->whereNumber('id');
            });
            Route::middleware('admin.can:vendors.manage')->group(function () {
                Route::post('/{id}/suspend', [AdminVendorController::class, 'suspend'])->whereNumber('id');
                Route::post('/{id}/reactivate', [AdminVendorController::class, 'reactivate'])->whereNumber('id');
            });
        });

        // Customer accounts: personal details masked unless revealed (and logged)
        Route::prefix('customers')->group(function () {
            Route::get('/complaints', [AdminCustomerController::class, 'complaintFeed'])->middleware('admin.can:customers.view|support.view');
            Route::middleware('admin.can:customers.view')->group(function () {
                Route::get('/summary', [AdminCustomerController::class, 'summary']);
                Route::get('/', [AdminCustomerController::class, 'index']);
                Route::get('/{id}', [AdminCustomerController::class, 'show'])->whereNumber('id');
            });
            Route::post('/{id}/reveal', [AdminCustomerController::class, 'reveal'])->whereNumber('id')->middleware('admin.can:customers.reveal');
            Route::middleware('admin.can:customers.manage')->group(function () {
                Route::post('/{id}/suspend', [AdminCustomerController::class, 'suspend'])->whereNumber('id');
                Route::post('/{id}/reactivate', [AdminCustomerController::class, 'reactivate'])->whereNumber('id');
            });
        });

        // Website content: banners, featured products, journal, announcements, site info
        Route::middleware('admin.can:content.manage')->prefix('content')->group(function () {
            Route::get('/overview', [AdminContentController::class, 'overview']);
            Route::get('/link-options', [AdminContentController::class, 'linkOptions']);

            Route::get('/banners', [AdminContentController::class, 'banners']);
            Route::post('/banners', [AdminContentController::class, 'saveBanner']);
            Route::post('/banners/{id}', [AdminContentController::class, 'saveBanner'])->whereNumber('id');
            Route::delete('/banners/{id}', [AdminContentController::class, 'deleteBanner'])->whereNumber('id');

            Route::get('/featured', [AdminContentController::class, 'featured']);
            Route::get('/featured/search', [AdminContentController::class, 'searchProducts']);
            Route::post('/featured', [AdminContentController::class, 'addFeatured']);
            Route::delete('/featured/{id}', [AdminContentController::class, 'removeFeatured'])->whereNumber('id');

            Route::get('/articles', [AdminContentController::class, 'articles']);
            Route::post('/articles', [AdminContentController::class, 'saveArticle']);
            Route::get('/articles/{id}', [AdminContentController::class, 'article'])->whereNumber('id');
            Route::post('/articles/{id}', [AdminContentController::class, 'saveArticle'])->whereNumber('id');
            Route::post('/articles/{id}/publish', [AdminContentController::class, 'publishArticle'])->whereNumber('id');
            Route::post('/articles/{id}/unpublish', [AdminContentController::class, 'unpublishArticle'])->whereNumber('id');
            Route::delete('/articles/{id}', [AdminContentController::class, 'deleteArticle'])->whereNumber('id');

            Route::get('/announcements', [AdminContentController::class, 'announcements']);
            Route::post('/announcements', [AdminContentController::class, 'saveAnnouncement']);
            Route::post('/announcements/{id}', [AdminContentController::class, 'saveAnnouncement'])->whereNumber('id');
            Route::delete('/announcements/{id}', [AdminContentController::class, 'deleteAnnouncement'])->whereNumber('id');

            Route::post('/{type}/reorder', [AdminContentController::class, 'reorder'])->whereIn('type', ['banners', 'announcements', 'featured']);

            Route::get('/settings', [AdminContentController::class, 'settings']);
            Route::put('/settings', [AdminContentController::class, 'saveSettings']);
        });

        // Notifications & support: inbox, announcements, alerts, activity
        Route::middleware('admin.can:support.view')->group(function () {
            Route::get('/support/summary', [AdminSupportController::class, 'summary']);
            Route::get('/support/tickets', [AdminSupportController::class, 'tickets']);
            Route::get('/support/tickets/{id}', [AdminSupportController::class, 'ticket'])->whereNumber('id');
            Route::get('/support/alerts', [AdminSupportController::class, 'alerts']);
            Route::get('/support/events', [AdminSupportController::class, 'events']);
            Route::get('/broadcasts', [AdminSupportController::class, 'broadcasts']);
        });
        Route::middleware('admin.can:support.reply')->group(function () {
            Route::post('/support/tickets/{id}/reply', [AdminSupportController::class, 'reply'])->whereNumber('id');
            Route::post('/support/tickets/{id}/status', [AdminSupportController::class, 'setStatus'])->whereNumber('id');
        });
        Route::middleware('admin.can:support.broadcast')->group(function () {
            Route::post('/broadcasts', [AdminSupportController::class, 'sendBroadcast']);
            Route::delete('/broadcasts/{id}', [AdminSupportController::class, 'retractBroadcast'])->whereNumber('id');
        });

        // Read-only finance: payments, refunds, earnings and exports
        Route::prefix('finance')->group(function () {
            Route::middleware('admin.can:finance.view')->group(function () {
                Route::get('/overview', [AdminFinanceController::class, 'overview']);
                Route::get('/transactions', [AdminFinanceController::class, 'transactions']);
                Route::get('/vendors', [AdminFinanceController::class, 'vendors']);
                Route::get('/refunds', [AdminFinanceController::class, 'refunds']);
                Route::get('/refunds/{id}', [AdminFinanceController::class, 'refundCase'])->whereNumber('id');
            });
            Route::middleware('admin.can:finance.export')->group(function () {
                Route::get('/transactions/export', [AdminFinanceController::class, 'exportTransactions']);
                Route::get('/report/export', [AdminFinanceController::class, 'exportReport']);
            });
        });

        // Security & system settings
        Route::prefix('security')->group(function () {
            Route::middleware('admin.can:security.view')->group(function () {
                Route::get('/admins', [SecurityController::class, 'admins']);
                Route::get('/roles', [SecurityController::class, 'roles']);
                Route::get('/audit', [SecurityController::class, 'audit']);
                Route::get('/sign-ins', [SecurityController::class, 'signIns']);
                Route::get('/settings', [SecurityController::class, 'settings']);
                Route::get('/health', [SecurityController::class, 'health']);
                Route::get('/api-errors', [SecurityController::class, 'apiErrors']);
            });
            Route::middleware('admin.can:security.manage')->group(function () {
                Route::post('/admins', [SecurityController::class, 'createAdmin']);
                Route::put('/admins/{id}', [SecurityController::class, 'updateAdmin'])->whereNumber('id');
                Route::post('/admins/{id}/active', [SecurityController::class, 'setAdminActive'])->whereNumber('id');
                Route::post('/admins/{id}/password', [SecurityController::class, 'resetAdminPassword'])->whereNumber('id');
                Route::post('/roles', [SecurityController::class, 'saveRole']);
                Route::put('/roles/{id}', [SecurityController::class, 'saveRole'])->whereNumber('id');
                Route::delete('/roles/{id}', [SecurityController::class, 'deleteRole'])->whereNumber('id');
                Route::put('/settings', [SecurityController::class, 'saveSettings']);
            });
        });
    });

}); // end auth:sanctum

// ============================================================
// 7. ADMIN — vendor applications, reports, products (admins only)
// ============================================================

Route::middleware(['token.auth', 'role:admin', 'admin.audit'])->prefix('admin')->group(function () {

    // ── Vendor applications ───────────────────────────────────────────────
    Route::middleware('admin.can:vendors.view')->group(function () {
        Route::get('/vendor-applications',             [VendorApplicationController::class, 'index']);
        Route::get('/vendor-applications/statistics',  [VendorApplicationController::class, 'statistics']);
        Route::get('/vendor-applications/export',      [VendorApplicationController::class, 'export']);
        Route::get('/vendor-applications/{id}',        [VendorApplicationController::class, 'show']);
        Route::get('/vendor-resubmission-fields',      [VendorApplicationResubmissionController::class, 'availableFields']);
        Route::get('/vendor-applications/{id}/resubmissions', [VendorApplicationResubmissionController::class, 'indexForApplication']);
    });
    Route::middleware('admin.can:vendors.manage')->group(function () {
        Route::put('/vendor-applications/{id}/status',   [VendorApplicationController::class, 'updateStatus']);
        Route::post('/vendor-applications/{id}/approve', [VendorApplicationController::class, 'approve']);
        Route::post('/vendor-applications/{id}/reject',  [VendorApplicationController::class, 'reject']);
        Route::post('/vendor-applications/{id}/resubmission-requests', [VendorApplicationResubmissionController::class, 'requestResubmission']);
        Route::post('/vendor-application-resubmissions/{id}/approve', [VendorApplicationResubmissionController::class, 'approveItem']);
        Route::post('/vendor-application-resubmissions/{id}/request-again', [VendorApplicationResubmissionController::class, 'requestAgain']);
    });

    // ── Product reports ───────────────────────────────────────────────────
    // ── Review management: customer reviews + reported listings ─────────────
    Route::middleware('admin.can:products.view')->group(function () {
        Route::get('/reviews/summary', [AdminReviewController::class, 'summary']);
        Route::get('/reviews/reports', [AdminReviewController::class, 'reports']);
        Route::get('/reviews', [AdminReviewController::class, 'index']);
    });
    Route::middleware('admin.can:products.moderate')->group(function () {
        Route::post('/reviews/{id}/hide', [AdminReviewController::class, 'hide'])->whereNumber('id');
        Route::post('/reviews/{id}/restore', [AdminReviewController::class, 'restore'])->whereNumber('id');
    });
    Route::post('/reports/{id}/review', [ProductReportController::class, 'review'])->middleware('admin.can:products.moderate');

    // ── Listings: moderation only; vendors keep editing their own products ──
    Route::middleware('admin.can:products.view')->group(function () {
        Route::get('/products/summary',  [AdminProductController::class, 'summary']);
        Route::get('/products',          [AdminProductController::class, 'index']);
        Route::get('/products/{id}',     [AdminProductController::class, 'show'])->whereNumber('id');
        Route::get('/taxonomies',        [AdminProductController::class, 'taxonomies']);
    });
    Route::post('/products/{id}/{action}', [AdminProductController::class, 'moderate'])
        ->whereNumber('id')->whereIn('action', ['approve', 'hide', 'remove', 'restore'])
        ->middleware('admin.can:products.moderate');

    // ── Categories, flower types, occasions ───────────────────────────────
    Route::middleware('admin.can:products.taxonomy')->group(function () {
        Route::post('/taxonomies',         [AdminProductController::class, 'storeTaxonomy']);
        Route::post('/taxonomies/reorder', [AdminProductController::class, 'reorderTaxonomies']);
        Route::put('/taxonomies/{id}',     [AdminProductController::class, 'updateTaxonomy'])->whereNumber('id');
        Route::delete('/taxonomies/{id}',  [AdminProductController::class, 'destroyTaxonomy'])->whereNumber('id');
    });

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

Route::get('/debug-cloudinary-upload', function () {
    try {
        // Test credentials
        $connection = \App\Helpers\CloudinaryHelper::testConnection();

        if (!$connection['connected']) {
            return response()->json([
                'status' => 'credentials_failed',
                'error'  => $connection['error'],
            ]);
        }

        // Test upload with a 1x1 pixel PNG
        $tmpFile = tempnam(sys_get_temp_dir(), 'cld_test_');
        file_put_contents($tmpFile, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        ));

        $result = \App\Helpers\CloudinaryHelper::upload($tmpFile, [
            'folder'        => 'debug_tests',
            'resource_type' => 'image',
        ]);

        // Clean up test file
        \App\Helpers\CloudinaryHelper::destroy($result['public_id']);
        @unlink($tmpFile);

        return response()->json([
            'status'     => 'upload_works',
            'public_id'  => $result['public_id'],
            'secure_url' => $result['secure_url'],
            'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        ]);

    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'failed',
            'error'  => $e->getMessage(),
            'type'   => get_class($e),
        ]);
    }
});
