<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Names the feature behind a request ("Checkout · Create order") so system
 * health never shows API paths or record IDs.
 */
class ApiAreas
{
    private const AREAS = [
        'AdminCustomerController' => 'Customer Management',
        'AdminVendorController' => 'Vendor Management',
        'VendorApplicationController' => 'Vendor applications',
        'VendorApplicationResubmissionController' => 'Vendor applications',
        'AdminOrderController' => 'Order Management',
        'AdminProductController' => 'Product Management',
        'AdminReviewController' => 'Review Management',
        'ProductReportController' => 'Product reports',
        'AdminContentController' => 'Promotion Management',
        'AdminSupportController' => 'Support Tickets',
        'FinanceController' => 'Transactions & refunds',
        'SubscriptionRevenueController' => 'Subscription Management',
        'AdminReportsController' => 'Reports & Analytics',
        'AdminDashboardController' => 'Admin dashboard',
        'SecurityController' => 'Security & settings',
        'LoginAuditLogController' => 'Audit Logs',
        'AuthController' => 'Sign-in & accounts',
        'EmployeeAuthController' => 'Staff sign-in',
        'CheckoutController' => 'Checkout',
        'PaymongoWebhookController' => 'PayMongo payments',
        'CustomerProductController' => 'Shop',
        'ProductController' => 'Vendor products',
        'ProductReviewController' => 'Product reviews',
        'SupportController' => 'Help & support',
        'NotificationController' => 'Notifications',
        'SiteContentController' => 'Shop content',
    ];

    /** From a stored action name, e.g. "App\Http\Controllers\CheckoutController@createOrder" */
    public static function fromAction(?string $action): ?string
    {
        if (!$action || !Str::contains($action, '@')) {
            return null;
        }
        $controller = class_basename(Str::before($action, '@'));
        $method = Str::after($action, '@');
        $area = self::AREAS[$controller] ?? Str::headline(Str::beforeLast($controller, 'Controller'));

        return $area . ' · ' . Str::ucfirst(Str::lower(Str::headline($method)));
    }

    /** For older log rows that only kept the method and path */
    public static function fromPath(string $method, string $path): string
    {
        try {
            $route = Route::getRoutes()->match(Request::create($path, $method));

            return self::fromAction($route->getActionName()) ?? 'Unnamed request';
        } catch (\Throwable) {
            return 'Unknown request';
        }
    }

    /** Error messages without SQL, file paths or numbers that could be IDs */
    public static function cleanMessage(?string $message): ?string
    {
        if (!$message) {
            return null;
        }
        $message = preg_replace('/\s*\((Connection|SQL):.*$/s', '', $message);       // Laravel query details
        $message = preg_replace('/SQLSTATE\[[^\]]*\]:?\s*/', '', $message);
        $message = preg_replace('#(?:[A-Za-z]:)?[\\\\/][^\s:]+#', '…', $message);       // file and URL paths
        $message = preg_replace('/\b\d+\b/', '#', $message);                          // IDs and other numbers

        return Str::limit(trim($message), 240);
    }
}
