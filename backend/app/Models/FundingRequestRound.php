<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One turn in agreeing how much to buy: an offer, a new ask, or the agreement */
class FundingRequestRound extends Model
{
    public const UPDATED_AT = null;

    // Finance can fund less than asked
    public const FINANCE = 'finance';
    // Procurement asks again for what they really need
    public const PROCUREMENT = 'procurement';
    // Procurement took Finance's offer
    public const ACCEPTED = 'accepted';
    // Finance approved what Procurement asked for
    public const APPROVED = 'approved';

    protected $table = 'funding_request_rounds';

    protected $fillable = ['funding_request_id', 'kind', 'actor_name', 'items', 'amount', 'note'];

    protected $casts = [
        'items' => 'array',
        'amount' => 'decimal:2',
    ];

    public function fundingRequest(): BelongsTo
    {
        return $this->belongsTo(FundingRequest::class);
    }
}
