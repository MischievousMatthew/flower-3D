<?php

namespace App\Services;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\VendorDirectory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Nobody is left hanging after asking for help:
 *  - every request gets an automatic reply straight away: its reference, who
 *    handles it (the shop or BloomCraft support), when to expect an answer and
 *    what happens next
 *  - if no person has replied in time, one follow-up says it's still being
 *    worked on, and the shop or support team is reminded
 * Automatic replies are marked is_automated: they never count as an answer.
 */
class SupportAutoReplies
{
    /** Hours before the follow-up, by who handles it */
    public const SHOP_FOLLOW_UP_HOURS = 24;
    public const SUPPORT_FOLLOW_UP_HOURS = 24;

    /** The reply posted the moment a request is sent */
    public static function acknowledge(SupportTicket $ticket, User $requester): void
    {
        if (! self::ready()) {
            return;
        }
        $first = self::firstName($requester);
        $ref = $ticket->reference;

        if ($ticket->vendor_id) {
            $shop = VendorDirectory::nameOf($ticket->vendor_id) ?? 'The shop';
            $order = $ticket->order?->order_number;
            $wants = SupportTicket::OUTCOMES[$ticket->desired_outcome] ?? null;
            $body = "Hi {$first}, thank you for telling us, and we're sorry about this.\n\n"
                . "Your complaint ({$ref}" . ($order ? ", order {$order}" : '') . ") has been sent to {$shop}, and they've been notified. "
                . 'Shops usually reply within ' . self::SHOP_FOLLOW_UP_HOURS . " hours, right here in this conversation.\n\n"
                . ($wants && $ticket->desired_outcome !== 'just_letting_know' ? "You asked for: {$wants}. The shop will tell you what they can do.\n\n" : '')
                . "You'll get a notification as soon as they answer. If you think of anything else, like photos of the problem, add it below. "
                . 'If the shop hasn\'t replied in ' . self::SHOP_FOLLOW_UP_HOURS . ' hours, we\'ll remind them.';
        } else {
            $urgent = $ticket->priority === 'high';
            $body = "Hi {$first}, thanks for reaching out. We've received your request ({$ref}).\n\n"
                . ($urgent
                    ? 'Because it\'s about ' . (in_array($ticket->category, ['payments', 'account'], true) ? 'payments or your account' : 'an urgent problem') . ', it\'s been put at the top of our queue. '
                    : '')
                . 'Our support team replies within ' . ($urgent ? 'a few hours' : 'one working day') . ", right here, and you'll get a notification.\n\n"
                . 'If you have more details, like screenshots or an order number, add them below. They help us sort it out faster.';
        }

        self::post($ticket, $body);
    }

    /**
     * Requests nobody has answered in time get one follow-up, and the shop or
     * support team is reminded. Checked whenever support lists are opened (the
     * free server has no scheduler).
     */
    public static function followUpOverdue(?int $requesterId = null, ?int $vendorId = null): void
    {
        if (! self::ready()) {
            return;
        }
        try {
            $cut = now()->subHours(min(self::SHOP_FOLLOW_UP_HOURS, self::SUPPORT_FOLLOW_UP_HOURS));
            SupportTicket::query()
                ->where('status', 'open')
                ->whereNull('followed_up_at')
                ->where('created_at', '<=', $cut)
                ->when($requesterId, fn ($q) => $q->where('requester_id', $requesterId))
                ->when($vendorId, fn ($q) => $q->where('vendor_id', $vendorId))
                // Only requests no person has answered yet
                ->whereDoesntHave('messages', fn ($m) => $m->where('is_automated', false)
                    ->where(fn ($w) => $w->where('from_admin', true)->orWhere(fn ($s) => $s->where('from_shop', true)->where('is_internal', false))))
                ->with('requester:id,name')
                ->limit(20)
                ->get()
                ->each(fn (SupportTicket $t) => self::followUp($t));
        } catch (\Throwable $e) {
            Log::warning('Support follow-ups skipped: ' . $e->getMessage());
        }
    }

    private static function followUp(SupportTicket $ticket): void
    {
        // Claim it first, so two people opening lists at once don't both follow up
        $claimed = SupportTicket::query()->whereKey($ticket->id)->whereNull('followed_up_at')->update(['followed_up_at' => now()]);
        if (! $claimed) {
            return;
        }
        $first = $ticket->requester ? self::firstName($ticket->requester) : 'there';

        if ($ticket->vendor_id) {
            $shop = VendorDirectory::nameOf($ticket->vendor_id) ?? 'the shop';
            self::post($ticket, "Hi {$first}, your complaint ({$ticket->reference}) is still open. We've reminded {$shop} to answer you, and you'll be notified the moment they do. "
                . 'Thank you for your patience.');
            self::notify($ticket->vendor_id, "A customer is waiting: {$ticket->reference}",
                "\"{$ticket->subject}\" has had no reply for over " . self::SHOP_FOLLOW_UP_HOURS . ' hours. Please answer it in Support cases.', '/erp/crm/cases');
        } else {
            self::post($ticket, "Hi {$first}, your request ({$ticket->reference}) is still with our support team. It hasn't been forgotten: we've moved it up the queue, "
                . "and you'll be notified as soon as someone replies.");
        }
    }

    private static function post(SupportTicket $ticket, string $body): void
    {
        try {
            SupportMessage::create([
                'ticket_id' => $ticket->id,
                'author_id' => null,
                'from_admin' => false,
                'from_shop' => false,
                'is_automated' => true,
                'author_name' => 'BloomCraft (automatic reply)',
                'body' => $body,
            ]);
        } catch (\Throwable $e) {
            // The request itself is saved; never fail it over the automatic reply
            Log::warning('Automatic support reply not posted', ['ticket_id' => $ticket->id, 'error' => $e->getMessage()]);
        }
    }

    private static function notify(int $userId, string $title, string $body, string $link): void
    {
        try {
            UserNotification::create(['user_id' => $userId, 'type' => 'support_reminder', 'title' => $title, 'body' => $body, 'link_url' => $link, 'important' => true]);
        } catch (\Throwable $e) {
            Log::warning('Support reminder not sent: ' . $e->getMessage());
        }
    }

    private static function firstName(User $user): string
    {
        return explode(' ', trim((string) $user->name))[0] ?: 'there';
    }

    private static function ready(): bool
    {
        static $ready = false;

        return $ready = $ready || Schema::hasColumn('support_messages', 'is_automated');
    }
}
