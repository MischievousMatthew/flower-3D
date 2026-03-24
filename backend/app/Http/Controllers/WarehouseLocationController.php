<?php

namespace App\Http\Controllers;

use App\Models\WarehouseLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Traits\ScopesOwner;
 
class WarehouseLocationController extends Controller
{
    use ScopesOwner;


    // ── List ───────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'include_inactive' => ['nullable', 'boolean'],
            'zone'             => ['nullable', 'string', 'max:100'],
            'refrigerated'     => ['nullable', 'boolean'],
            'warehouse_id'     => ['nullable', 'integer', 'exists:warehouses,id'],
            'unowned'          => ['nullable', 'boolean'],
        ]);

        $ownerId = $this->getOwnerId();

        $query = WarehouseLocation::query()
            ->where('owner_id', $ownerId)
            ->when(
                ! $request->boolean('include_inactive'),
                fn ($q) => $q->where('is_active', true)
            )
            ->when(
                $request->warehouse_id,
                fn ($q, $id) => $q->where('warehouse_id', $id)
                    ->whereHas('warehouse', fn($wq) => $wq->where('owner_id', $ownerId))
            )
            ->when(
                $request->boolean('unowned'),
                fn ($q) => $q->whereNull('warehouse_id') // ← free locations only
            )
            ->when(
                $request->zone,
                fn ($q, $zone) => $q->where('zone', $zone)
            )
            ->when(
                $request->has('refrigerated'),
                fn ($q) => $q->where('is_refrigerated', $request->boolean('refrigerated'))
            )
            ->orderBy('zone')
            ->orderBy('name');

        // Return full stats when scoped to a warehouse (for WarehouseLocationList.vue)
        $withStats = $request->boolean('with_stats') || $request->filled('warehouse_id');

        $locations = $query->get()->map(
            fn ($loc) => $withStats ? $this->formatWithStats($loc) : $this->format($loc)
        );

        return response()->json([
            'success' => true,
            'data'    => $locations,
            'total'   => $locations->count(),
        ]);
    }

    // ── Single ─────────────────────────────────────────────────────────────

    public function show(int $id): JsonResponse
    {
        $location = WarehouseLocation::where('owner_id', $this->getOwnerId())->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $this->formatWithStats($location),
        ]);
    }

    // ── Create ─────────────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:100'],
            'code'            => ['required', 'string', 'max:30', 'unique:warehouse_locations,code'],
            'zone'            => ['nullable', 'string', 'max:100'],
            'is_refrigerated' => ['boolean'],
            'capacity_units'  => ['nullable', 'integer', 'min:1'],
            'is_active'       => ['boolean'],
            'warehouse_id'    => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);

        $data['code']     = strtoupper($data['code']);
        $data['owner_id'] = $this->getOwnerId();

        $location = WarehouseLocation::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Location created.',
            'data'    => $this->format($location),
        ], 201);
    }

    // ── Update ─────────────────────────────────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $location = WarehouseLocation::where('owner_id', $this->getOwnerId())->findOrFail($id);

        $data = $request->validate([
            'name'            => ['sometimes', 'string', 'max:100'],
            'code'            => ['sometimes', 'string', 'max:30', "unique:warehouse_locations,code,{$id}"],
            'zone'            => ['nullable', 'string', 'max:100'],
            'is_refrigerated' => ['boolean'],
            'capacity_units'  => ['nullable', 'integer', 'min:1'],
            'is_active'       => ['boolean'],
            'warehouse_id'    => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);

        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        // Guard: block reassigning an owned location to a different warehouse.
        if (
            isset($data['warehouse_id']) &&
            $data['warehouse_id'] !== null &&
            $location->warehouse_id !== null &&
            (int) $data['warehouse_id'] !== (int) $location->warehouse_id
        ) {
            return response()->json([
                'success' => false,
                'message' => "Location [{$location->name}] already belongs to another warehouse. Unlink it first.",
            ], 422);
        }

        $location->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Location updated.',
            'data'    => $this->format($location->fresh()),
        ]);
    }

    // ── Toggle active ──────────────────────────────────────────────────────

    public function toggle(int $id): JsonResponse
    {
        $location = WarehouseLocation::where('owner_id', $this->getOwnerId())->findOrFail($id);
        $location->update(['is_active' => ! $location->is_active]);

        return response()->json([
            'success'   => true,
            'message'   => 'Location ' . ($location->is_active ? 'activated' : 'deactivated') . '.',
            'is_active' => $location->is_active,
        ]);
    }

    // ── Delete ─────────────────────────────────────────────────────────────

    public function destroy(int $id): JsonResponse
    {
        $location = WarehouseLocation::where('owner_id', $this->getOwnerId())->findOrFail($id);

        $activeBatches = $location->activeBatches()->count();

        if ($activeBatches > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete: {$activeBatches} active batch(es) are assigned to this location.",
            ], 422);
        }

        $location->delete();

        return response()->json([
            'success' => true,
            'message' => 'Location deleted.',
        ]);
    }

    // ── Formatters ─────────────────────────────────────────────────────────

    private function format(WarehouseLocation $loc): array
    {
        return [
            'id'              => $loc->id,
            'warehouse_id'    => $loc->warehouse_id,
            'name'            => $loc->name,
            'code'            => $loc->code,
            'zone'            => $loc->zone,
            'is_refrigerated' => $loc->is_refrigerated,
            'capacity_units'  => $loc->capacity_units,
            'is_active'       => $loc->is_active,
            'created_at'      => $loc->created_at?->toDateTimeString(),
        ];
    }

    private function formatWithStats(WarehouseLocation $loc): array
    {
        $base = $this->format($loc);

        $activeBatches = $loc->activeBatches()->with('product:id,product_name,sku')->get();
        $currentUnits  = $activeBatches->sum('qty_remaining');
        $capacityPct   = $loc->capacity_units
            ? round(($currentUnits / $loc->capacity_units) * 100, 1)
            : null;

        return array_merge($base, [
            'current_units'  => $currentUnits,
            'capacity_pct'   => $capacityPct,
            'is_full'        => $loc->capacity_units ? $currentUnits >= $loc->capacity_units : false,
            'active_batches' => $activeBatches->count(),
            'batch_summary'  => [
                'fresh'   => $activeBatches->where('condition_status', 'fresh')->count(),
                'aging'   => $activeBatches->where('condition_status', 'aging')->count(),
                'wilting' => $activeBatches->where('condition_status', 'wilting')->count(),
                'spoiled' => $activeBatches->where('condition_status', 'spoiled')->count(),
            ],
        ]);
    }
}