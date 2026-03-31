<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Helpers\CloudinaryHelper;

class Supplier extends Model
{
    protected $fillable = [
        'owner_id',
        'company_name',
        'contact_person', 
        'email',
        'phone', 
        'address', 
        'status', 
        'logo',
    ];

    protected $appends = ['logo_url'];

    protected $casts = [
        'status' => 'string',
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

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}