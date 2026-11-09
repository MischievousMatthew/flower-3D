<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money sent to a supplier for a funding request, or returned by one */
class FundingRequestPayment extends Model
{
    public const KIND_PAYMENT = 'payment';
    public const KIND_REFUND = 'refund';
    public const KIND_CREDIT = 'credit';

    protected $table = 'funding_request_payments';

    protected $fillable = [
        'owner_id',
        'funding_request_id',
        'purchase_order_id',
        'kind',
        'amount',
        'payment_method',
        'payment_channel',
        'reference',
        'paid_on',
        'note',
        'recorded_by_name',
    ];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_on' => 'date:Y-m-d',
    ];

    public function fundingRequest(): BelongsTo
    {
        return $this->belongsTo(FundingRequest::class);
    }
}
