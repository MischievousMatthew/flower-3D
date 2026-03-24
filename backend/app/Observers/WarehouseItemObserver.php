<?php

namespace App\Observers;

use App\Models\WarehouseItem;
use App\Services\BarcodeService;

/**
 * WarehouseItemObserver
 *
 * Automatically generates and persists a scannable barcode string
 * whenever a new WarehouseItem is created.
 *
 * Register in App\Providers\AppServiceProvider::boot():
 *   WarehouseItem::observe(WarehouseItemObserver::class);
 */
class WarehouseItemObserver
{
    public function __construct(protected BarcodeService $barcodeService) {}

    /**
     * After the item is saved for the first time, assign its barcode.
     * Uses `created` (not `creating`) so the model already has an `id`.
     */
    public function created(WarehouseItem $item): void
    {
        // Skip if a barcode was explicitly provided (e.g. migration seed)
        if ($item->barcode) return;

        $this->barcodeService->assignItemBarcode($item);
    }
}