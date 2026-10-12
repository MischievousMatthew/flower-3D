<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Order payments and vendor subscription payments as one read-only list,
 * for the admin Finance page and Dashboard. Gateway references are masked.
 */
final class PaymentLedger
{
    public const STATES = ['successful', 'pending', 'failed', 'refunded', 'voided'];

    /**
     * Order payments and subscription payments as one list. The state is the
     * payment's outcome, not the order's fulfilment status.
     */
    public static function query(): Builder
    {
        $orders = DB::table('orders')->selectRaw("
            'order' as source,
            id as source_id,
            order_number as reference,
            vendor_id,
            user_id as customer_id,
            payment_method as method,
            case when payment_method = 'cod' then 'cod' else 'paymongo' end as channel,
            case
                when payment_status = 'paid' then 'successful'
                when payment_status = 'refunded' then 'refunded'
                when payment_status = 'failed' or status = 'failed' then 'failed'
                when status in ('cancelled', 'returned', 'refunded') then 'voided'
                else 'pending'
            end as state,
            total_amount as amount,
            coalesce(paid_at, created_at) as occurred_at,
            created_at,
            coalesce(paymongo_payment_intent_id, paymongo_source_id) as gateway_ref,
            status as order_status
        ");

        $subscriptions = DB::table('vendor_subscription_checkouts')->selectRaw("
            'subscription' as source,
            id as source_id,
            reference_number as reference,
            vendor_id,
            cast(null as bigint) as customer_id,
            'subscription' as method,
            'paymongo' as channel,
            case
                when status = 'paid' then 'successful'
                when status = 'failed' then 'failed'
                when status = 'cancelled' then 'voided'
                when created_at < ? then 'voided'
                else 'pending'
            end as state,
            amount,
            coalesce(paid_at, created_at) as occurred_at,
            created_at,
            coalesce(paymongo_payment_id, paymongo_checkout_session_id) as gateway_ref,
            cast(null as varchar) as order_status
        ", [now()->subDay()]); // a subscription checkout left unpaid for a day was abandoned


        return DB::query()->fromSub($orders->unionAll($subscriptions), 'ledger');
    }


    public static function format(Collection $rows): array
    {
        $vendors = VendorDirectory::details($rows->pluck('vendor_id'));
        $customers = User::query()->whereIn('id', $rows->pluck('customer_id')->filter()->unique())
            ->get(['id', 'name', 'surname', 'email'])->keyBy('id');

        return $rows->map(fn ($row) => [
            'key' => $row->source . '-' . $row->source_id,
            'source' => $row->source,
            'source_id' => (int) $row->source_id,
            'reference' => $row->reference,
            'channel' => $row->channel,
            'method' => $row->method,
            'method_label' => self::methodLabel($row->method),
            'state' => $row->state,
            'amount' => round((float) $row->amount, 2),
            'occurred_at' => $row->occurred_at ? CarbonImmutable::parse($row->occurred_at)->toIso8601String() : null,
            'gateway_ref' => PaymentMask::reference($row->gateway_ref),
            'order_status' => $row->order_status,
            'vendor' => $vendors[$row->vendor_id] ?? VendorDirectory::unknown($row->vendor_id),
            'customer' => $row->customer_id ? [
                'name' => self::personName($customers[$row->customer_id] ?? null),
                'email' => $customers[$row->customer_id]->email ?? null,
            ] : null,
        ])->values()->all();
    }


    public static function methodLabel(?string $method): string
    {
        return match ($method) {
            'cod' => 'Cash on delivery',
            'ewallet' => 'E-wallet',
            'bank_transfer' => 'Bank transfer',
            'gcash' => 'GCash',
            'maya' => 'Maya',
            'card' => 'Card',
            'subscription' => 'GCash / Maya',
            null, '' => '—',
            default => ucfirst(str_replace('_', ' ', $method)),
        };
    }


    public static function personName(?User $user): ?string
    {
        return $user ? (trim($user->name . ' ' . ($user->surname ?? '')) ?: null) : null;
    }

}
