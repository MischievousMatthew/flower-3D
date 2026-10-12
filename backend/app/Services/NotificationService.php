<?php

namespace App\Services;

use App\Models\UserNotification;
use Illuminate\Support\Collection;

/** Puts items in people's notification inboxes */
class NotificationService
{
    /**
     * @param iterable<int> $userIds
     * @return int how many people were notified
     */
    public function notify(iterable $userIds, string $type, string $title, ?string $body = null, ?string $link = null, bool $important = false, ?int $broadcastId = null): int
    {
        $now = now();
        $count = 0;

        Collection::make($userIds)->filter()->unique()->chunk(500)->each(function ($chunk) use ($type, $title, $body, $link, $important, $broadcastId, $now, &$count) {
            UserNotification::insert($chunk->map(fn ($id) => [
                'user_id' => $id,
                'broadcast_id' => $broadcastId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'link_url' => $link,
                'important' => $important,
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()->all());
            $count += $chunk->count();
        });

        return $count;
    }
}
