<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in vendor's or customer's inbox: announcements from
 * BloomCraft and replies to their support requests.
 */
class NotificationController extends Controller
{
    /** GET /api/notifications */
    public function index(Request $request): JsonResponse
    {
        $items = UserNotification::query()->where('user_id', $request->user()->id)
            ->latest()->orderByDesc('id')->limit(min(max((int) $request->input('limit', 20), 1), 50))->get();

        return response()->json([
            'success' => true,
            'data' => $items->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'link_url' => $n->link_url,
                'important' => $n->important,
                'read' => $n->read_at !== null,
                'created_at' => $n->created_at?->toIso8601String(),
            ]),
            'unread' => $this->unread($request),
        ]);
    }

    /** GET /api/notifications/unread-count */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'unread' => $this->unread($request)]);
    }

    /** POST /api/notifications/{id}/read */
    public function markRead(Request $request, int $id): JsonResponse
    {
        UserNotification::query()->where('user_id', $request->user()->id)->whereKey($id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['success' => true, 'unread' => $this->unread($request)]);
    }

    /** POST /api/notifications/read-all */
    public function markAllRead(Request $request): JsonResponse
    {
        UserNotification::query()->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['success' => true, 'unread' => 0]);
    }

    private function unread(Request $request): int
    {
        return UserNotification::query()->where('user_id', $request->user()->id)->whereNull('read_at')->count();
    }
}
