<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Website information: name, about text, contact details, social links */
class SiteSetting extends Model
{
    public const KEYS = [
        'site_name', 'tagline', 'about', 'contact_email', 'contact_phone',
        'address', 'business_hours', 'socials', 'footer_note',
    ];

    protected $fillable = ['key', 'value'];

    protected $casts = ['value' => 'json'];

    /** @return array<string, mixed> */
    public static function snapshot(): array
    {
        $stored = static::query()->whereIn('key', self::KEYS)->pluck('value', 'key')->all();

        return collect(self::KEYS)->mapWithKeys(fn ($key) => [$key => $stored[$key] ?? null])->all();
    }

    /** How the shop's banner slideshow plays. Kept out of KEYS so the website-info form never resets it. */
    public const SLIDESHOW_DEFAULTS = [
        'seconds' => 6,           // time each slide stays up
        'autoplay' => true,       // move on by itself
        'pause_on_hover' => true, // hold while the pointer is over it
        'show_arrows' => true,    // previous / next buttons
        'show_dots' => true,      // slide dots
    ];

    /** How the announcement bar at the top of the shop behaves. Also kept out of KEYS. */
    public const ANNOUNCEMENT_BAR_DEFAULTS = [
        'seconds' => 7,              // time each announcement stays up
        'autoplay' => true,          // move on by itself
        'pause_on_hover' => true,    // hold while the pointer is over it
        'show_arrows' => true,       // ‹ › buttons
        'dismissible' => true,       // customers may close announcements
        'dismiss_for' => 'forever',  // forever | 1 | 7 (days a closed announcement stays hidden)
    ];

    public const DISMISS_FOR = ['forever', '1', '7'];

    /**
     * The rules for the tags on products (see App\Services\ProductTags).
     * Stock tags (In stock / Low stock / Out of stock) follow the stock itself
     * and aren't set here. Also kept out of KEYS.
     */
    public const PRODUCT_TAG_DEFAULTS = [
        // The ranking, top first: a shop card shows only the highest tag a product has.
        // The admin drags these into order.
        'order' => self::PRODUCT_TAG_ORDER,
        'sale_enabled' => true,        // SALE: discounted by at least…
        'sale_min_percent' => 5,
        'best_enabled' => true,        // BEST SELLER: the top N by units sold in the last D days
        'best_top' => 10,
        'best_days' => 90,
        'best_min_sold' => 3,
        'new_enabled' => true,         // NEW ARRIVAL: added in the last D days, at most N
        'new_days' => 14,
        'new_max' => 12,
        'trend_enabled' => true,       // TRENDING: the top N by recent interest (orders, carts, hearts)
        'trend_top' => 8,
        'trend_days' => 7,
        'trend_min_score' => 3,
        'rated_enabled' => true,       // TOP RATED: at least this rating from this many reviews, at most N
        'rated_min_rating' => 4.5,
        'rated_min_reviews' => 3,
        'rated_max' => 20,
        'recommended_enabled' => true, // RECOMMENDED: the admin's Featured products
        'seasonal_enabled' => true,    // SEASONAL: products for a season, not all year
    ];

    /** Every tag a shop card can show, in the starting order */
    public const PRODUCT_TAG_ORDER = [
        'out_of_stock', 'sale', 'best_seller', 'trending', 'new_arrival',
        'top_rated', 'recommended', 'seasonal', 'you_may_like',
    ];

    /** @return array<string, mixed> */
    public static function productTags(): array
    {
        $rules = static::group('product_tags', self::PRODUCT_TAG_DEFAULTS);
        $rules['order'] = self::tagOrder($rules['order']);

        return $rules;
    }

    /** A saved order, made whole: unknown tags dropped, missing ones added at the end */
    public static function tagOrder($order): array
    {
        $known = array_values(array_intersect(is_array($order) ? $order : [], self::PRODUCT_TAG_ORDER));

        return array_values(array_unique([...$known, ...self::PRODUCT_TAG_ORDER]));
    }

    /** @return array<string, mixed> */
    public static function slideshow(): array
    {
        return static::group('slideshow', self::SLIDESHOW_DEFAULTS);
    }

    /** @return array<string, mixed> */
    public static function announcementBar(): array
    {
        return static::group('announcement_bar', self::ANNOUNCEMENT_BAR_DEFAULTS);
    }

    /** A stored settings row merged over its defaults; unknown keys are dropped. */
    private static function group(string $key, array $defaults): array
    {
        $stored = static::query()->where('key', $key)->value('value');
        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }

        return array_merge($defaults, array_intersect_key(is_array($stored) ? $stored : [], $defaults));
    }
}
