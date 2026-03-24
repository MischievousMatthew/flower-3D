<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cart extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
        'price',
        'color',
        'size',
        'notes',
        'customizations',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'customizations' => 'array',
    ];

    protected $appends = [
        'subtotal',
        'total',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->with(['images']);
    }

    // Accessors
    public function getSubtotalAttribute(): float
    {
        return (float) $this->price * $this->quantity;
    }

    public function getTotalAttribute(): float
    {
        return $this->subtotal;
    }

    // Check if product is still available
    public function getIsAvailableAttribute(): bool
    {
        return $this->product && 
               $this->product->status === 'active' &&
               $this->product->quantity_in_stock >= $this->quantity;
    }

    // Check if product is low stock
    public function getIsLowStockAttribute(): bool
    {
        return $this->product && $this->product->quantity_in_stock <= $this->product->min_stock_level;
    }

    // Get stock status
    public function getStockStatusAttribute(): string
    {
        if (!$this->product) return 'unavailable';
        
        if ($this->product->quantity_in_stock === 0) {
            return 'out_of_stock';
        } elseif ($this->product->quantity_in_stock < $this->quantity) {
            return 'insufficient_stock';
        } elseif ($this->isLowStock) {
            return 'low_stock';
        }
        
        return 'available';
    }
}