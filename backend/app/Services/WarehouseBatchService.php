<?php

namespace App\Services;

use App\Models\Product;
use App\Models\WarehouseBatch;
use App\Models\WarehouseBatchLog;
use App\Models\WarehouseLocation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class WarehouseBatchService
{
    // ── Read ───────────────────────────────────────────────────────────────

    /**
     * All active batches, grouped by product, with computed condition.
     * Used by the warehouse floor view.
     *
     * @param array{location_id?: int, condition?: string, search?: string, product_id?: int} $filters
     */
    public function floorView(int $ownerId, array $filters = []): array
    {
        $query = WarehouseBatch::with(['product.images' => fn ($q) => $q->where('is_primary', true)->limit(1), 'location'])
            ->where('owner_id', $ownerId)
            ->where('status', 'active')
            ->when($filters['location_id'] ?? null, fn ($q, $id) => $q->where('warehouse_location_id', $id))
            ->when($filters['product_id'] ?? null, fn ($q, $id) => $q->where('product_id', $id))
            ->when($filters['search'] ?? null, function ($q, $s) {
                $q->whereHas('product', fn ($pq) => $pq->where('product_name', 'like', "%{$s}%")
                    ->orWhere('sku', 'like', "%{$s}%"))
                  ->orWhere('batch_number', 'like', "%{$s}%");
            })
            ->orderBy('expiration_date')
            ->get();

        $batches = $query->map(fn ($b) => $this->formatBatch($b));

        // Apply condition filter AFTER computing derived condition
        if ($filters['condition'] ?? null) {
            $batches = $batches->filter(fn ($b) => $b['computed_condition'] === $filters['condition']);
        }

        return [
            'batches' => $batches->values(),
            'summary' => [
                'total_batches'  => $batches->count(),
                'total_units'    => $batches->sum('qty_remaining'),
                'fresh'          => $batches->where('computed_condition', 'fresh')->count(),
                'aging'          => $batches->where('computed_condition', 'aging')->count(),
                'wilting'        => $batches->where('computed_condition', 'wilting')->count(),
                'spoiled'        => $batches->where('computed_condition', 'spoiled')->count(),
                'expiring_today' => $batches->filter(fn ($b) => ($b['days_remaining'] ?? 999) <= 1)->count(),
            ],
        ];
    }

    /**
     * Look up a batch by barcode (for scanner workflow).
     */
    public function findByBarcode(string $barcode, int $ownerId): ?WarehouseBatch
    {
        return WarehouseBatch::with(['product', 'location', 'logs' => fn ($q) => $q->limit(5)])
            ->where('owner_id', $ownerId)
            ->where('barcode', $barcode)
            ->first();
    }

    /**
     * All batches for a single product.
     */
    public function batchesForProduct(int $productId, int $ownerId): Collection
    {
        return WarehouseBatch::with('location')
            ->where('product_id', $productId)
            ->where('owner_id', $ownerId)
            ->orderByDesc('received_date')
            ->get()
            ->map(fn ($b) => $this->formatBatch($b));
    }

    /**
     * Full log history for a batch.
     */
    public function batchLogs(int $batchId, int $ownerId): Collection
    {
        return WarehouseBatchLog::with('performer:id,name')
            ->whereHas('batch', fn($q) => $q->where('owner_id', $ownerId))
            ->where('warehouse_batch_id', $batchId)
            ->orderByDesc('created_at')
            ->get();
    }

    // ── Write ──────────────────────────────────────────────────────────────

    /**
     * Receive a new batch into the warehouse.
     * Automatically syncs product.quantity_in_stock after saving.
     */
    public function receiveBatch(array $data, int $ownerId): WarehouseBatch
    {
        $product  = Product::where('owner_id', $ownerId)->findOrFail($data['product_id']);
        $location = isset($data['warehouse_location_id'])
            ? WarehouseLocation::where('warehouse_id', function($q) use ($ownerId) {
                $q->select('id')->from('warehouses')->where('owner_id', $ownerId);
            })->find($data['warehouse_location_id'])
            : null;

        $batchNumber = WarehouseBatch::generateBatchNumber(
            $product->sku,
            $data['received_date']
        );

        $batch = WarehouseBatch::create([
            'owner_id'               => $ownerId,
            'product_id'             => $product->id,
            'warehouse_location_id'  => $location?->id,
            'storage_location'       => $location?->name,
            'batch_number'           => $batchNumber,
            'barcode'                => $this->generateBarcode($batchNumber),
            'lot_number'             => $data['lot_number'] ?? null,
            'received_date'          => $data['received_date'],
            'harvest_date'           => $data['harvest_date'] ?? null,
            'expiration_date'        => $data['expiration_date'] ?? null,
            'freshness_days'         => $data['freshness_days'] ?? null,
            'qty_received'           => $data['qty_received'],
            'qty_remaining'          => $data['qty_received'],
            'condition_status'       => 'fresh',
            'status'                 => 'active',
            'notes'                  => $data['notes'] ?? null,
        ]);

        // Log the initial receipt
        $batch->logs()->create([
            'performed_by'   => Auth::id(),
            'event_type'     => 'QUANTITY_ADJUSTED',
            'qty_change'     => $data['qty_received'],
            'qty_after'      => $data['qty_received'],
            'to_location'    => $location?->name,
            'notes'          => 'Batch received',
        ]);

        // ── Sync product stock ─────────────────────────────────────────────
        $this->syncProductStock($product->id);

        return $batch->load('product', 'location');
    }

    /**
     * Update the condition of a batch (manual or system-triggered).
     * If condition becomes 'discarded', the batch status flips to depleted
     * and the product stock is resynced.
     */
    public function updateCondition(int $batchId, string $condition, int $ownerId, ?string $notes = null): WarehouseBatch
    {
        $batch = WarehouseBatch::where('owner_id', $ownerId)->findOrFail($batchId);
        $batch->updateCondition($condition, Auth::id(), $notes);

        // If the batch was discarded/spoiled and marked depleted, resync stock
        if ($condition === 'discarded') {
            $this->syncProductStock($batch->product_id);
        }

        return $batch->fresh();
    }

    /**
     * Cull (discard) units from a batch — spoiled flowers removed from stock.
     * Always resyncs product stock after reducing qty_remaining.
     */
    public function cullBatch(int $batchId, int $qty, int $ownerId, ?string $notes = null): WarehouseBatch
    {
        $batch = WarehouseBatch::where('owner_id', $ownerId)->findOrFail($batchId);
        $batch->reduceQuantity($qty, 'CULLED', Auth::id(), $notes);

        // ── Sync product stock ─────────────────────────────────────────────
        $this->syncProductStock($batch->product_id);

        return $batch->fresh();
    }

    /**
     * Transfer a batch to a different storage location.
     * Stock total doesn't change, but we resync for consistency.
     */
    public function transferBatch(int $batchId, int $locationId, int $ownerId, ?string $notes = null): WarehouseBatch
    {
        $batch    = WarehouseBatch::where('owner_id', $ownerId)->findOrFail($batchId);
        $location = WarehouseLocation::where('warehouse_id', function($q) use ($ownerId) {
            $q->select('id')->from('warehouses')->where('owner_id', $ownerId);
        })->where('owner_id', $ownerId)->findOrFail($locationId);

        $batch->transferTo($location, Auth::id(), $notes);

        return $batch->fresh(['location']);
    }

    /**
     * Scan a barcode — log the scan event and return the batch details.
     */
    public function scanBatch(string $barcode, int $ownerId): array
    {
        $batch = $this->findByBarcode($barcode, $ownerId);

        if (! $batch) {
            return ['found' => false, 'barcode' => $barcode];
        }

        $batch->logs()->create([
            'performed_by' => Auth::id(),
            'event_type'   => 'SCANNED',
            'qty_after'    => $batch->qty_remaining,
            'notes'        => 'Scanned in warehouse',
        ]);

        return [
            'found' => true,
            'batch' => $this->formatBatch($batch->load('product', 'location')),
        ];
    }

    // ── Stock sync ─────────────────────────────────────────────────────────

    /**
     * Recalculate products.quantity_in_stock from the sum of
     * qty_remaining across all ACTIVE batches for that product.
     *
     * Call this after any operation that changes qty_remaining or
     * batch status (receive, cull, discard).
     */
    public function syncProductStock(int $productId): void
    {
        $total = WarehouseBatch::where('product_id', $productId)
            ->where('status', 'active')
            ->sum('qty_remaining');

        Product::where('id', $productId)
            ->update(['quantity_in_stock' => $total]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function formatBatch(WarehouseBatch $b): array
    {
        $today         = now()->startOfDay();
        $daysRemaining = $b->expiration_date
            ? (int) $today->diffInDays($b->expiration_date, false)
            : null;

        // Compute freshness condition from dates
        $computedCondition = 'unknown';
        if ($daysRemaining !== null) {
            if ($daysRemaining < 0) {
                $computedCondition = 'spoiled';
            } elseif ($b->freshness_days) {
                $ratio = $daysRemaining / $b->freshness_days;
                $computedCondition = match(true) {
                    $ratio > 0.60 => 'fresh',
                    $ratio > 0.40 => 'aging',
                    $ratio > 0.20 => 'wilting',
                    default       => 'spoiled',
                };
            } else {
                $computedCondition = match(true) {
                    $daysRemaining > 5 => 'fresh',
                    $daysRemaining > 2 => 'aging',
                    $daysRemaining > 0 => 'wilting',
                    default            => 'spoiled',
                };
            }
        }

        return [
            'id'                 => $b->id,
            'batch_number'       => $b->batch_number,
            'barcode'            => $b->barcode,
            'lot_number'         => $b->lot_number,
            'received_date'      => $b->received_date?->toDateString(),
            'harvest_date'       => $b->harvest_date?->toDateString(),
            'expiration_date'    => $b->expiration_date?->toDateString(),
            'freshness_days'     => $b->freshness_days,
            'days_remaining'     => $daysRemaining,
            'qty_received'       => $b->qty_received,
            'qty_remaining'      => $b->qty_remaining,
            'condition_status'   => $b->condition_status,
            'computed_condition' => $computedCondition,
            'status'             => $b->status,
            'storage_location'   => $b->storage_location,
            'notes'              => $b->notes,

            'product' => $b->product ? [
                'id'                     => $b->product->id,
                'product_name'           => $b->product->product_name,
                'sku'                    => $b->product->sku,
                'flower_type'            => $b->product->flower_type,
                'color'                  => $b->product->color,
                'requires_refrigeration' => $b->product->requires_refrigeration,
                'is_fragile'             => $b->product->is_fragile,
                'image_url'              => $b->product->images->first()?->image_url,
            ] : null,

            'location' => $b->location ? [
                'id'               => $b->location->id,
                'name'             => $b->location->name,
                'code'             => $b->location->code,
                'is_refrigerated'  => $b->location->is_refrigerated,
            ] : null,
        ];
    }

    private function generateBarcode(string $batchNumber): string
    {
        return 'WB-' . strtoupper(base_convert((string) time(), 10, 36))
             . '-' . strtoupper(Str::random(4));
    }
}