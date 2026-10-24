<?php

namespace App\Models;

use App\Helpers\CloudinaryHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A cost the shop recorded itself (flowers, rent, utilities, packaging…). */
class VendorExpense extends Model
{
    public const CATEGORIES = [
        'flowers' => 'Flowers',
        'supplies' => 'Supplies',
        'packaging' => 'Packaging',
        'delivery' => 'Delivery & fuel',
        'utilities' => 'Utilities',
        'rent' => 'Rent',
        'marketing' => 'Marketing',
        'equipment' => 'Equipment',
        'maintenance' => 'Repairs & maintenance',
        'fees' => 'Bank & service fees',
        'taxes' => 'Taxes & permits',
        'other' => 'Other',
    ];

    /**
     * Bought to sell: counted as a stock purchase, like an approved funding
     * request. Its cost reaches profit through cost of goods when it sells,
     * so it isn't an operating expense as well.
     */
    public const STOCK_CATEGORIES = ['flowers'];

    /** Up to this many receipts and documents per expense */
    public const MAX_FILES = 5;

    public const PAYMENT_METHODS = [
        'cash' => 'Cash',
        'gcash' => 'GCash',
        'maya' => 'Maya',
        'bank_transfer' => 'Bank transfer',
        'card' => 'Card',
        'other' => 'Other',
    ];

    protected $fillable = [
        'owner_id', 'category', 'description', 'amount', 'spent_on', 'payment_method',
        'payee', 'reference', 'receipt_path', 'receipt_type', 'notes', 'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'spent_on' => 'date',
    ];

    public function receiptUrl(): ?string
    {
        if (! $this->receipt_path) {
            return null;
        }

        return CloudinaryHelper::getUrl($this->receipt_path, $this->receipt_type === 'pdf' ? 'raw' : 'image');
    }

    public function files(): HasMany
    {
        return $this->hasMany(VendorExpenseFile::class)->orderBy('id');
    }

    public function isStock(): bool
    {
        return in_array($this->category, self::STOCK_CATEGORIES, true);
    }
}
