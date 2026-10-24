<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\CustomerInquiry;
use App\Models\Message;
use App\Traits\ResolvesOwner;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /crm/communication — what the team needs at the top of the inbox:
 * unread messages, chats waiting on a reply (and since when), how fast the
 * shop has been replying lately, and questions still unanswered.
 */
class CrmCommunicationController extends Controller
{
    use ResolvesOwner;

    private const RECENT_DAYS = 7;
    private const OVERDUE_HOURS = 24;

    public function summary(Request $request): JsonResponse
    {
        $vendorId = $this->resolveOwnerId($request);
        $conversations = Conversation::withoutGlobalScopes()->where('vendor_id', $vendorId)->whereNull('vendor_archived_at')
            ->get(['id', 'customer_id', 'unread_count_vendor']);
        $customerOf = $conversations->pluck('customer_id', 'id');

        // Chats whose latest message is from the customer: waiting since their first unanswered message
        $waiting = [];
        if ($conversations->isNotEmpty()) {
            $recent = Message::withoutGlobalScopes()->whereIn('conversation_id', $customerOf->keys())
                ->where('created_at', '>=', now()->subDays(60))
                ->orderBy('conversation_id')->orderBy('created_at')
                ->get(['conversation_id', 'sender_id', 'created_at']);
            foreach ($recent->groupBy('conversation_id') as $conversationId => $thread) {
                $since = null;
                foreach ($thread as $m) {
                    if ((int) $m->sender_id === (int) $customerOf[$conversationId]) {
                        $since ??= $m->created_at;
                    } else {
                        $since = null;
                    }
                }
                if ($since) {
                    $waiting[$conversationId] = $since->toIso8601String();
                }
            }
        }

        $openInquiries = CustomerInquiry::query()->where('vendor_id', $vendorId)->where('status', 'open');

        return response()->json(['success' => true, 'data' => [
            'unread_messages' => (int) $conversations->sum('unread_count_vendor'),
            'unread_chats' => $conversations->where('unread_count_vendor', '>', 0)->count(),
            'waiting_chats' => count($waiting),
            'waiting' => (object) $waiting,
            'oldest_waiting_at' => $waiting ? min($waiting) : null,
            'avg_reply_minutes' => $this->averageReply($customerOf),
            'open_inquiries' => (clone $openInquiries)->count(),
            'overdue_inquiries' => (clone $openInquiries)->where('created_at', '<', now()->subHours(self::OVERDUE_HOURS))->count(),
        ]]);
    }

    /** Average minutes from a customer's message to the shop's next reply, over the last week */
    private function averageReply($customerOf): ?int
    {
        if ($customerOf->isEmpty()) {
            return null;
        }
        $messages = Message::withoutGlobalScopes()->whereIn('conversation_id', $customerOf->keys())
            ->where('created_at', '>=', Carbon::now()->subDays(self::RECENT_DAYS))
            ->orderBy('conversation_id')->orderBy('created_at')
            ->get(['conversation_id', 'sender_id', 'created_at']);

        $waits = [];
        foreach ($messages->groupBy('conversation_id') as $conversationId => $thread) {
            $askedAt = null;
            foreach ($thread as $m) {
                if ((int) $m->sender_id === (int) $customerOf[$conversationId]) {
                    $askedAt ??= $m->created_at;
                } elseif ($askedAt) {
                    $waits[] = $askedAt->diffInMinutes($m->created_at);
                    $askedAt = null;
                }
            }
        }

        return $waits ? (int) round(array_sum($waits) / count($waits)) : null;
    }
}
