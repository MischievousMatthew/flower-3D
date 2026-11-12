<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\User;
use App\Models\UserNotification;

/**
 * Announcements from Admin → Promotion Management also land in customers'
 * notifications (From BloomCraft), once each.
 *
 * They're delivered when a customer next checks their notifications, so a
 * scheduled announcement arrives when it starts showing, and customers who
 * join later still get the ones showing now. Editing an announcement
 * updates the notice; switching it off or deleting it takes it back out.
 */
class AnnouncementNotices
{
    public const TYPE = 'announcement';
    private const KEY = 'site_announcement:';

    /** Put the announcements showing now into this customer's inbox */
    public function deliver(User $user): void
    {
        if (! $user->isCustomer()) {
            return;
        }

        $showing = Announcement::showing()->where('kind', Announcement::ANNOUNCEMENT)->get();
        if ($showing->isEmpty()) {
            return;
        }

        $have = UserNotification::query()->where('user_id', $user->id)
            ->whereIn('event_key', $showing->map(fn ($a) => self::KEY . $a->id))
            ->pluck('event_key')->all();

        $rows = $showing->reject(fn ($a) => in_array(self::KEY . $a->id, $have, true))
            ->map(function (Announcement $a) use ($user) {
                $at = $a->starts_at ?? $a->created_at ?? now();

                return [
                    'user_id' => $user->id,
                    'type' => self::TYPE,
                    'event_key' => self::KEY . $a->id,
                    'title' => mb_substr($a->title, 0, 160),
                    'body' => $a->message,
                    'link_url' => self::link($a),
                    'important' => false,
                    'created_at' => $at,
                    'updated_at' => now(),
                ];
            })->values()->all();

        if ($rows) {
            // Two requests at once can't add it twice (user_id + event_key is unique)
            UserNotification::insertOrIgnore($rows);
        }
    }

    /** After an admin saves or deletes an announcement: keep inboxes in step */
    public function refresh(Announcement $a, bool $deleted = false): void
    {
        $notices = UserNotification::query()->where('event_key', self::KEY . $a->id);

        if ($deleted) {
            $notices->delete();
            return;
        }
        // Switched off, or turned into a promotion: gone for whoever hasn't read it yet
        if (! $a->is_active || $a->kind !== Announcement::ANNOUNCEMENT) {
            $notices->whereNull('read_at')->delete();
            return;
        }

        $notices->update([
            'title' => mb_substr($a->title, 0, 160),
            'body' => $a->message,
            'link_url' => self::link($a),
            'updated_at' => now(),
        ]);
    }

    /** Where the notice's Open button goes, in the inbox's link format */
    public static function link(Announcement $a): ?string
    {
        $value = trim((string) $a->link_value);
        $route = fn (string $name, array $query = []) => 'route:' . $name . ($query ? '?' . http_build_query($query) : '');

        $link = match ($a->link_type) {
            'category' => $value !== '' ? $route('Shop', ['category' => $value]) : null,
            'occasion' => $value !== '' ? $route('Shop', ['occasion' => $value]) : null,
            'store' => $value !== '' ? $route('VendorStorefront', ['storeId' => $value]) : null,
            'article' => $value !== '' ? '/journal/' . rawurlencode($value) : null,
            'products' => $route('Shop', ['section' => 'products']),
            'url' => $value !== '' ? $value : null,
            default => null,
        };

        return $link !== null ? mb_substr($link, 0, 500) : null;
    }
}
