<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Notices from BloomCraft to the shops and customers an admin action affects
 * (checkout paused, a listing hidden, a review hidden, an account suspended…).
 * They land in the same bell as everything else, marked important so they
 * sit at the top until read. A notice that can't be sent never stops the action.
 */
class PlatformNotices
{
    public const TYPE = 'platform';

    public function __construct(private NotificationService $notifications) {}

    public function toUser(?int $userId, string $title, ?string $body = null, ?string $link = null, bool $important = true): void
    {
        if (!$userId) {
            return;
        }
        $this->send([$userId], $title, $body, $link, $important);
    }

    /** Every shop that can sell (suspended shops are skipped) */
    public function toAllShops(string $title, ?string $body = null, ?string $link = null, bool $important = true): int
    {
        try {
            $ids = User::query()->where('role', User::ROLE_VENDOR)
                ->where(fn ($q) => $q->where('is_suspended', false)->orWhereNull('is_suspended'))
                ->pluck('id');
        } catch (\Throwable $e) {
            Log::warning('Platform notice: shops could not be listed', ['message' => $e->getMessage()]);

            return 0;
        }

        return $this->send($ids, $title, $body, $link, $important);
    }

    private function send(iterable $userIds, string $title, ?string $body, ?string $link, bool $important): int
    {
        try {
            return $this->notifications->notify($userIds, self::TYPE, $title, $body, $link, $important);
        } catch (\Throwable $e) {
            Log::warning('Platform notice could not be sent', ['title' => $title, 'message' => $e->getMessage()]);

            return 0;
        }
    }
}
