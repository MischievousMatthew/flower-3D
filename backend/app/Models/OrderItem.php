<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_description',
        'product_image',
        'quantity',
        'unit_price',
        'subtotal',
        'color',
        'size',
        'notes',
        'customizations',
        'model_3d_path',
        'model_3d_url',
        'vendor_id', // Store vendor ID at purchase time
        'product_data_snapshot', // JSON snapshot of all product data
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'quantity' => 'integer',
        'customizations' => 'array',
        'product_data_snapshot' => 'array',
    ];

    // Relationships
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    /**
     * Accessor for product name - use snapshot first
     */
    public function getProductNameAttribute($value)
    {
        if ($this->product_data_snapshot && isset($this->product_data_snapshot['name'])) {
            return $this->product_data_snapshot['name'];
        }
        return $value;
    }

    /**
     * Accessor for product image - use snapshot first
     */
    public function getProductImageAttribute($value)
    {
        if ($this->product_data_snapshot && isset($this->product_data_snapshot['image'])) {
            return $this->product_data_snapshot['image'];
        }
        return $value;
    }

    /**
     * Accessor for 3D model URL - use snapshot first
     */
    public function getModel3dUrlAttribute($value)
    {
        if ($this->product_data_snapshot && isset($this->product_data_snapshot['model_3d_url'])) {
            return $this->product_data_snapshot['model_3d_url'];
        }
        return $value;
    }

    /**
     * Check if item has 3D model
     */
    public function getHas3dModelAttribute()
    {
        return !empty($this->model_3d_url);
    }

    /**
     * Get preparation time from snapshot
     */
    public function getPreparationTimeAttribute($value)
    {
        if ($this->product_data_snapshot && isset($this->product_data_snapshot['preparation_time'])) {
            return $this->product_data_snapshot['preparation_time'];
        }
        return $value;
    }

    /**
     * Create a snapshot of product data
     */
    public static function createSnapshot(Product $product, $customizations = null, $color = null, $size = null)
    {
        // Get primary image
        $primaryImage = $product->primaryImage;
        $imageUrl = $primaryImage ? $primaryImage->image_url : null;

        // Get 3D model if exists
        $model3d = $product->models()->first();
        $model3dUrl = $model3d ? $model3d->model_url : null;

        // Get vendor store name
        $vendorStoreName = null;
        if ($product->owner && $product->owner->vendorApplication) {
            $vendorApp = $product->owner->vendorApplication;
            $vendorStoreName = $vendorApp->store_name ?? $vendorApp->business_name;
        }

        return [
            'id' => $product->id,
            'name' => $product->product_name,
            'description' => $product->description,
            'image' => $imageUrl,
            'price' => (float) ($product->discount_price ?: $product->selling_price),
            'original_price' => (float) $product->selling_price,
            'discount_price' => $product->discount_price ? (float) $product->discount_price : null,
            'preparation_time' => (int) ($product->preparation_time ?? $product->supplier_lead_time ?? 0),
            'category' => $product->category,
            'flower_type' => $product->flower_type,
            'weight' => $product->weight,
            'care_instructions' => $product->care_instructions,
            'freshness_days' => $product->freshness_days,
            'model_3d_url' => $model3dUrl,
            'vendor_id' => $product->user_id,
            'vendor_name' => $vendorStoreName ?? $product->owner->name ?? 'Local Vendor',
            'customizations' => $customizations,
            'color' => $color,
            'size' => $size,
            'captured_at' => now()->toISOString(),
            'stock_at_purchase' => $product->quantity_in_stock,
        ];
    }
}
