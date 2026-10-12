<?php

namespace App\Http\Controllers;

use App\Models\ProductTaxonomy;
use Illuminate\Http\JsonResponse;

/**
 * The categories, flower types and occasions vendors can choose from
 * (managed by admins).
 */
class ProductTaxonomyController extends Controller
{
    /**
     * GET /api/product-taxonomies
     */
    public function index(): JsonResponse
    {
        $items = ProductTaxonomy::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['type', 'value', 'label', 'description'])
            ->groupBy('type')
            ->map(fn ($rows) => $rows->map->only(['value', 'label', 'description'])->values());

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $items[ProductTaxonomy::CATEGORY] ?? [],
                'flower_types' => $items[ProductTaxonomy::FLOWER_TYPE] ?? [],
                'occasions' => $items[ProductTaxonomy::OCCASION] ?? [],
            ],
        ]);
    }
}
