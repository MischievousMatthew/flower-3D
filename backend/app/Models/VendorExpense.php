<?php

namespace App\Models;

use App\Helpers\CloudinaryHelper;
use Illuminate\Database\Eloquent\Model;

/** An operating expense the shop recorded itself (rent, utilities, packaging…). */
class VendorExpense extends Model
{
    public const CATEGORIES = [
        'rent' => 'Rent',
        'utilities' => 'Utilities',
        'supplies' => 'Packaging & supplies',
        'delivery' => 'Delivery & fuel',
        'marketing' => 'Marketing',
        'equipment' => 'Equipment',
        'maintenance' => 'Repairs & maintenance',
        'fees' => 'Bank & service fees',
        'taxes' => 'Taxes & permits',
        'other' => 'Other',
    ];

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
}
