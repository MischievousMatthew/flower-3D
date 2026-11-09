<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns the record an admin action touched into a readable name
 * ("Cassandra Gacuma", "order ORD-0004", "“Roses”"), so audit logs never
 * show database IDs or API paths.
 */
class AuditTargets
{
    /**
     * @param string       $action  e.g. "AdminCustomerController@suspend"
     * @param string|null  $id      the {id} route parameter, if any
     * @param Request|null $request for actions that create something (no id yet)
     */
    public static function label(string $action, ?string $id, ?Request $request = null): ?string
    {
        try {
            [$controller, $method] = array_pad(explode('@', $action, 2), 2, '');

            if ($id === null || $id === '') {
                return self::fromRequest($controller, $method, $request);
            }

            return match ($controller) {
                'AdminCustomerController' => self::person($id),
                'AdminVendorController' => self::store($id),
                'SecurityController' => in_array($method, ['saveRole', 'deleteRole'], true)
                    ? self::column('admin_roles', $id, 'name', 'role “%s”')
                    : self::person($id),
                'AdminOrderController' => self::column('orders', $id, 'order_number', 'order %s'),
                'AdminProductController' => in_array($method, ['updateTaxonomy', 'destroyTaxonomy'], true)
                    ? self::column('product_taxonomies', $id, 'label', '“%s”')
                    : self::product($id),
                'ProductReportController' => ($productId = DB::table('product_reports')->where('id', $id)->value('product_id'))
                    ? 'report on ' . self::product((string) $productId)
                    : null,
                'AdminReviewController' => self::review($id),
                'AdminContentController' => match (true) {
                    Str::contains($method, 'Banner') => self::column('site_banners', $id, 'title', 'banner “%s”'),
                    Str::contains($method, 'Article') => self::column('articles', $id, 'title', 'article “%s”'),
                    Str::contains($method, 'Announcement') => self::column('announcements', $id, 'title', '“%s”'),
                    $method === 'removeFeatured' => ($productId = DB::table('featured_products')->where('id', $id)->value('product_id'))
                        ? self::product((string) $productId)
                        : null,
                    default => null,
                },
                'AdminSupportController' => $method === 'retractBroadcast'
                    ? self::column('broadcasts', $id, 'title', '“%s”')
                    : self::ticket($id),
                'VendorApplicationController' => self::column('vendor_applications', $id, 'store_name', '%s'),
                'VendorApplicationResubmissionController' => self::application(
                    $method === 'requestResubmission'
                        ? $id
                        : (string) DB::table('vendor_application_resubmissions')->where('id', $id)->value('vendor_application_id')
                ),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    /** Readable details: no *_id fields, role IDs become role names */
    public static function details(?array $details): ?array
    {
        if (!$details) {
            return null;
        }
        if (isset($details['admin_role_id'])) {
            $details['role'] = DB::table('admin_roles')->where('id', $details['admin_role_id'])->value('name') ?? 'a role';
        }

        $clean = collect($details)->reject(fn ($v, $k) => $k === 'id' || Str::endsWith((string) $k, ['_id', '_ids']))->all();

        return $clean ?: null;
    }

    private static function fromRequest(string $controller, string $method, ?Request $request): ?string
    {
        if (!$request) {
            return null;
        }
        $title = fn (string $field, string $format = '“%s”') => ($v = trim((string) $request->input($field))) !== '' ? sprintf($format, Str::limit($v, 80)) : null;

        return match ("{$controller}@{$method}") {
            'SecurityController@createAdmin' => trim($request->input('name') . ' ' . $request->input('surname')) ?: null,
            'SecurityController@saveRole' => $title('name', 'role “%s”'),
            'AdminProductController@storeTaxonomy' => $title('label'),
            'AdminContentController@saveBanner' => $title('title', 'banner “%s”'),
            'AdminContentController@saveArticle' => $title('title', 'article “%s”'),
            'AdminContentController@saveAnnouncement', 'AdminSupportController@sendBroadcast' => $title('title'),
            'AdminContentController@addFeatured' => $request->filled('product_id') ? self::product((string) $request->input('product_id')) : null,
            default => null,
        };
    }

    private static function column(string $table, string $id, string $column, string $format): ?string
    {
        $value = DB::table($table)->where('id', $id)->value($column);

        return $value !== null && $value !== '' ? sprintf($format, Str::limit((string) $value, 80)) : null;
    }

    private static function person(string $id): ?string
    {
        $user = DB::table('users')->where('id', $id)->first(['name', 'surname']);

        return $user ? trim($user->name . ' ' . ($user->surname ?? '')) : null;
    }

    private static function store(string $id): ?string
    {
        $details = VendorDirectory::details([(int) $id]);

        return $details[(int) $id]['store_name'] ?? self::person($id);
    }

    private static function product(string $id): ?string
    {
        return self::column('products', $id, 'product_name', '“%s”');
    }

    private static function review(string $id): ?string
    {
        $review = DB::table('product_reviews')->where('id', $id)->first(['product_id', 'user_id']);
        if (!$review) {
            return null;
        }
        $by = self::person((string) $review->user_id);

        return trim('review on ' . (self::product((string) $review->product_id) ?? 'a product') . ($by ? " by {$by}" : ''));
    }

    private static function ticket(string $id): ?string
    {
        $ticket = DB::table('support_tickets')->where('id', $id)->first(['reference', 'subject']);

        return $ticket ? trim(($ticket->reference ? $ticket->reference . ' · ' : '') . Str::limit((string) $ticket->subject, 60)) : null;
    }

    private static function application(string $id): ?string
    {
        return $id !== '' ? self::column('vendor_applications', $id, 'store_name', '%s') : null;
    }
}
