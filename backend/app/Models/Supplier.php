<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Helpers\CloudinaryHelper;

class Supplier extends Model
{
    use BelongsToOwner;
    protected $fillable = [
        'owner_id',
        'company_name',
        'contact_person', 
        'email',
        'phone', 
        'address', 
        'status', 
        'logo',
        'supplier_type',
        'delivery_days',
        'lead_time_days',
        'order_cutoff',
        'minimum_order',
        'payment_terms',
        'notes',
    ];

    /** What a supplier mainly provides */
    public const TYPES = ['flower_farm', 'wholesaler', 'packaging', 'supplies'];

    protected $appends = ['logo_url'];

    protected $casts = [
        'status'         => 'string',
        'delivery_days'  => 'array',
        'lead_time_days' => 'integer',
        'minimum_order'  => 'decimal:2',
    ];

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? CloudinaryHelper::getUrl($this->logo) : null;
    }


    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * A supplier has many branch / alternate contacts.
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class);
    }

    /**
     * A supplier has many purchase orders.
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /**
     * What the supplier sells and for how much.
     */
    public function products(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    /**
     * The shop's ratings of this supplier.
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(SupplierRating::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
