<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One choice in the vendor product form: a category, flower type or
 * occasion. `value` is what products store, so it never changes once
 * created; admins edit the label, order and whether it's offered.
 */
class ProductTaxonomy extends Model
{
    public const CATEGORY = 'category';
    public const FLOWER_TYPE = 'flower_type';
    public const OCCASION = 'occasion';
    public const TYPES = [self::CATEGORY, self::FLOWER_TYPE, self::OCCASION];

    protected $fillable = ['type', 'value', 'label', 'description', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type)->orderBy('sort_order')->orderBy('label');
    }

    /** Values vendors may pick for a type */
    public static function activeValues(string $type): array
    {
        return static::query()->where('type', $type)->where('is_active', true)->pluck('value')->all();
    }
}
