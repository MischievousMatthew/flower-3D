<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A receipt or supporting document attached to an expense, kept private on Cloudinary */
class VendorExpenseFile extends Model
{
    protected $fillable = [
        'owner_id', 'vendor_expense_id', 'public_id', 'resource_type', 'format', 'file_name', 'size', 'uploaded_by',
    ];

    protected $casts = ['size' => 'integer'];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(VendorExpense::class, 'vendor_expense_id');
    }

    public function isImage(): bool
    {
        return in_array(strtolower((string) $this->format), ['jpg', 'jpeg', 'png', 'webp'], true);
    }
}
