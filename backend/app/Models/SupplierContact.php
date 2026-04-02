<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierContact extends Model
{
    use BelongsToOwner;

    protected $fillable = [
        'owner_id',
        'supplier_id',
        'company_name',
        'contact_person',
        'email',
        'phone',
        'address',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * A contact belongs to one supplier.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
