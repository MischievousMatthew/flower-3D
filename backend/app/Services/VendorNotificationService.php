<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\CustomerInquiry;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderRequest;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Models\UserNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Turns what happens in a shop (new orders, payments, low stock, customer
 * messages and questions, reviews, return requests) into notifications in
 * the vendor's inbox. Each event has a key, so it notifies once however
 * often this runs.
 *
 * Links use "route:RouteName?key=value" so the bell can open the exact
 * order or conversation without putting IDs in the address bar.
 */
class VendorNotificationService
{
    public const TYPES = [
        'orders' => ['label' => 'New orders', 'hint' => 'When a customer places an order'],
        'payments' => ['label' => 'Payments', 'hint' => 'When an online payment comes in'],
        'stock' => ['label' => 'Low stock', 'hint' => 'When a product runs low or sells out'],
        'messages' => ['label' => 'Customer messages', 'hint' => 'When a customer sends you a chat message'],
        'inquiries' => ['label' => 'Customer questions', 'hint' => 'When someone asks about a product or order'],
        'reviews' => ['label' => 'Reviews', 'hint' => 'When a customer rates one of your products'],
        'returns' => ['label' => 'Returns & refunds', 'hint' => 'When a customer asks for a return or refund'],
    ];

    /** Don't look further back than this the first time. */
    private const FIRST_LOOKBACK_DAYS = 7;

    /** How often events are checked per vendor. */
    private const MIN_INTERVAL_SECONDS = 30;

    private static ?bool $ready = null;

    public static function ready(): bool
    {
        return self::$ready ??= Schema::hasTable('notification_preferences')
            && Schema::hasColumn('user_notifications', 'event_key')
            && Schema::hasTable('customer_inquiries');
    }

    public function preferences(User $vendor): array
    {
        $saved = self::ready()
            ? json_decode((string) DB::table('notification_preferences')->where('user_id', $vendor->id)->value('settings'), true)
            : null;

        return collect(self::TYPES)->mapWithKeys(fn ($t, $key) => [$key => (bool) ($saved[$key] ?? true)])->all();
    }

    public function savePreferences(User $vendor, array $settings): array
    {
        $clean = collect(self::TYPES)->mapWithKeys(fn ($t, $key) => [$key => (bool) ($settings[$key] ?? true)])->all();
        DB::table('notification_preferences')->updateOrInsert(
            ['user_id' => $vendor->id],
            ['settings' => json_encode($clean), 'updated_at' => now(), 'created_at' => now()],
        );

        return $clean;
    }

    /** Bring the vendor's inbox up to date. Cheap to call often. */
    public function sync(User $vendor, bool $force = false): void
    {
        if (! self::ready() || ! $vendor->isVendor()) {
            return;
        }

        try {
            $state = DB::table('notification_preferences')->where('user_id', $vendor->id)->first();
            $last = $state?->synced_at ? Carbon::parse($state->synced_at) : null;
            if (! $force && $last && $last->gt(now()->subSeconds(self::MIN_INTERVAL_SECONDS))) {
                return;
            }
            $firstRun = $last === null;
            // A little overlap, so nothing created during the last check is missed (keys stop repeats)
            $since = $firstRun ? now()->subDays(self::FIRST_LOOKBACK_DAYS) : $last->copy()->subMinutes(5);
            $wants = $this->preferences($vendor);
            $rows = [];

            if ($wants['orders']) {
                $rows = array_merge($rows, $this->orders($vendor, $since));
            }
            if ($wants['payments']) {
                $rows = array_merge($rows, $this->payments($vendor, $since));
            }
            if ($wants['messages']) {
                $rows = array_merge($rows, $this->messages($vendor, $since));
            }
            if ($wants['inquiries']) {
                $rows = array_merge($rows, $this->inquiries($vendor, $since));
            }
            if ($wants['reviews']) {
                $rows = array_merge($rows, $this->reviews($vendor, $since));
            }
            if ($wants['returns']) {
                $rows = array_merge($rows, $this->returns($vendor, $since));
            }
            if ($wants['stock']) {
                $rows = array_merge($rows, $this->stock($vendor));
            }

            $now = now();
            $insert = collect($rows)->map(fn ($r) => [
                'user_id' => $vendor->id,
                'type' => $r['type'],
                'event_key' => $r['key'],
                'title' => Str::limit($r['title'], 190),
                'body' => $r['body'] ? Str::limit($r['body'], 480) : null,
                'link_url' => $r['link'] ?? null,
                'important' => $r['important'] ?? false,
                // The first time, older events arrive already read so the inbox doesn't open with a pile
                'read_at' => $firstRun && $r['at']->lt($now->copy()->subDay()) ? $now : null,
                'created_at' => $r['at'],
                'updated_at' => $now,
            ])->all();

            foreach (array_chunk($insert, 200) as $chunk) {
                UserNotification::query()->insertOrIgnore($chunk);
            }

            DB::table('notification_preferences')->updateOrInsert(
                ['user_id' => $vendor->id],
                ['synced_at' => $now, 'updated_at' => $now] + ($state ? [] : ['settings' => json_encode($wants), 'created_at' => $now]),
            );
        } catch (\Throwable $e) {
            Log::warning('Vendor notification sync failed: ' . $e->getMessage());
        }
    }

    // ── Event sources ──────────────────────────────────────────────────────

    private function orders(User $vendor, Carbon $since): array
    {
        return Order::withoutGlobalScopes()->where('vendor_id', $vendor->id)->where('created_at', '>', $since)
            // Online payments that were never finished aren't real orders yet
            ->where(fn ($q) => $q->where('payment_method', '!=', 'ewallet')->orWhereNull('payment_method')->orWhereNotNull('paid_at'))
            ->with('user:id,name,surname')
            ->get()
            ->map(fn (Order $o) => [
                'type' => 'order',
                'key' => "order:{$o->id}",
                'title' => "New order {$o->order_number}",
                'body' => trim(($o->user?->full_name ?? 'A customer') . ' · ' . $this->money($o->total_amount)
                    . ($o->reservation_date ? ' · for ' . Carbon::parse($o->reservation_date)->format('M j') : '')),
                'link' => "route:VendorOrderDetails?order={$o->id}",
                'important' => true,
                'at' => $o->created_at,
            ])->all();
    }

    private function payments(User $vendor, Carbon $since): array
    {
        return Order::withoutGlobalScopes()->where('vendor_id', $vendor->id)
            ->whereNotNull('paid_at')->where('paid_at', '>', $since)
            ->where(fn ($q) => $q->whereNull('payment_method')->orWhere('payment_method', '!=', 'cod'))
            ->get()
            ->map(fn (Order $o) => [
                'type' => 'payment',
                'key' => "paid:{$o->id}",
                'title' => 'Payment received · ' . $this->money($o->total_amount),
                'body' => "For order {$o->order_number}" . ($o->payment_method ? ' · ' . Str::headline($o->payment_method) : ''),
                'link' => "route:VendorOrderDetails?order={$o->id}",
                'at' => Carbon::parse($o->paid_at),
            ])->all();
    }

    private function messages(User $vendor, Carbon $since): array
    {
        $conversations = Conversation::query()->where('vendor_id', $vendor->id)->where('is_active', true)
            ->with('customer:id,name,surname')->get()->keyBy('id');
        if ($conversations->isEmpty()) {
            return [];
        }

        return Message::query()->whereIn('conversation_id', $conversations->keys())
            ->where('sender_id', '!=', $vendor->id)
            ->where('is_read', false)->where('created_at', '>', $since)
            ->orderBy('id')->get()
            ->groupBy('conversation_id')
            ->map(function ($messages, $conversationId) use ($conversations) {
                $first = $messages->first();
                $last = $messages->last();
                $name = $conversations[$conversationId]->customer?->full_name ?? 'A customer';
                $count = $messages->count();

                return [
                    'type' => 'message',
                    // One notification per burst of unread messages
                    'key' => "chat:{$conversationId}:{$first->id}",
                    'title' => $count > 1 ? "{$count} new messages from {$name}" : "New message from {$name}",
                    'body' => $last->message ? Str::limit($last->message, 140) : 'Sent an attachment',
                    'link' => "route:VendorChat?conversation={$conversationId}",
                    'at' => $first->created_at,
                ];
            })->values()->all();
    }

    private function inquiries(User $vendor, Carbon $since): array
    {
        return CustomerInquiry::query()->where('vendor_id', $vendor->id)->where('created_at', '>', $since)
            ->with(['customer:id,name,surname', 'product:id,product_name', 'order:id,order_number'])
            ->get()
            ->map(fn (CustomerInquiry $q) => [
                'type' => 'inquiry',
                'key' => "inquiry:{$q->id}",
                'title' => ($q->customer?->full_name ?? 'A customer') . ' asked about '
                    . ($q->product ? $q->product->product_name : ($q->order ? "order {$q->order->order_number}" : 'your shop')),
                'body' => Str::limit($q->question, 160),
                'link' => "route:VendorInquiries?inquiry={$q->id}",
                'important' => true,
                'at' => $q->created_at,
            ])->all();
    }

    private function reviews(User $vendor, Carbon $since): array
    {
        $productIds = Product::withoutGlobalScopes()->where('owner_id', $vendor->id)->pluck('product_name', 'id');
        if ($productIds->isEmpty()) {
            return [];
        }

        return ProductReview::query()->whereIn('product_id', $productIds->keys())->where('created_at', '>', $since)
            ->with('user:id,name')
            ->get()
            ->map(fn (ProductReview $r) => [
                'type' => 'review',
                'key' => "review:{$r->id}",
                'title' => ($r->is_anonymous ? 'Someone' : ($r->user?->name ?? 'A customer')) . " rated {$productIds[$r->product_id]} {$r->rating}★",
                'body' => $r->comment ? Str::limit($r->comment, 160) : null,
                'link' => "route:VendorReviews?review={$r->id}",
                'important' => $r->rating <= 2,
                'at' => $r->created_at,
            ])->all();
    }

    private function returns(User $vendor, Carbon $since): array
    {
        return OrderRequest::query()->where('created_at', '>', $since)
            ->whereHas('order', fn ($q) => $q->withoutGlobalScopes()->where('vendor_id', $vendor->id))
            ->with('order:id,order_number')
            ->get()
            ->map(fn (OrderRequest $r) => [
                'type' => 'return',
                'key' => "case:{$r->id}",
                'title' => 'Return / refund request for ' . ($r->order?->order_number ?? 'an order'),
                'body' => $r->reason ? Str::limit($r->reason, 160) : null,
                'link' => 'route:VendorReturns',
                'important' => true,
                'at' => $r->created_at,
            ])->all();
    }

    /** Products that are out or low right now. Restocking re-arms the alert. */
    private function stock(User $vendor): array
    {
        $products = Product::withoutGlobalScopes()->whereNull('deleted_at')->where('owner_id', $vendor->id)
            ->get(['id', 'product_name', 'quantity_in_stock', 'min_stock_level', 'status']);

        $rows = [];
        $healthy = [];
        foreach ($products as $p) {
            $stock = (int) $p->quantity_in_stock;
            $min = $p->min_stock_level !== null ? (int) $p->min_stock_level : null;
            $state = $stock <= 0 ? 'out' : ($min && $stock <= $min ? 'low' : null);
            if (! $state) {
                $healthy[] = $p->id;
                continue;
            }
            $rows[] = [
                'type' => 'stock',
                'key' => "stock:{$p->id}:{$state}",
                'title' => $state === 'out' ? "{$p->product_name} is sold out" : "{$p->product_name} is running low",
                'body' => $state === 'out' ? 'Customers can\'t order it until you add stock.' : "{$stock} left (you're warned at {$min}).",
                'link' => 'route:VendorInventory',
                'important' => $state === 'out',
                'at' => now(),
            ];
        }

        // Back in stock: free the keys so the next drop notifies again (the old notifications stay)
        $healthy = array_flip($healthy);
        $rearm = UserNotification::query()->where('user_id', $vendor->id)->where('event_key', 'like', 'stock:%')
            ->pluck('event_key', 'id')
            ->filter(fn ($key) => isset($healthy[(int) explode(':', $key)[1]]))
            ->keys();
        if ($rearm->isNotEmpty()) {
            UserNotification::query()->whereIn('id', $rearm)->update(['event_key' => null]);
        }

        return $rows;
    }

    private function money($amount): string
    {
        return '₱' . number_format((float) $amount, 2);
    }
}
