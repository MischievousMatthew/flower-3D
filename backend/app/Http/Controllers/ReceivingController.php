<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PurchaseOrderReceipt;
use App\Services\ReceivingService;
use App\Traits\ScopesOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Receiving & quality inspection. Open to warehouse staff (Warehouse: view /
 * create) and to purchasing staff (Purchase orders: view / edit).
 */
class ReceivingController extends Controller
{
    use ScopesOwner;

    public function __construct(private readonly ReceivingService $receiving) {}

    /** GET /receiving/open-orders */
    public function openOrders(Request $request): JsonResponse
    {
        if ($denied = $this->deny($request, 'view')) return $denied;

        return response()->json(['data' => $this->receiving->openOrders($this->getOwnerId())]);
    }

    /** GET /receiving: recent deliveries, newest first */
    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->deny($request, 'view')) return $denied;
        $filters = $request->validate([
            'issues'   => ['nullable', 'boolean'],
            'order_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $page = PurchaseOrderReceipt::where('owner_id', $this->getOwnerId())
            ->with(['order:id,order_number,supplier_id', 'order.supplier:id,company_name', 'files'])
            ->when($filters['issues'] ?? false, fn ($q) => $q->where(fn ($w) => $w->where('rejected_total', '>', 0)->orWhere('missing_total', '>', 0)))
            ->when($filters['order_id'] ?? null, fn ($q, $id) => $q->where('purchase_order_id', $id))
            ->latest('id')
            ->paginate($filters['per_page'] ?? 10);

        return response()->json($page);
    }

    /** GET /receiving/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        if ($denied = $this->deny($request, 'view')) return $denied;

        return response()->json(['data' => PurchaseOrderReceipt::where('owner_id', $this->getOwnerId())
            ->with(['lines', 'files', 'order:id,order_number,supplier_id,status', 'order.supplier:id,company_name'])
            ->findOrFail($id)]);
    }

    /** POST /receiving: one delivery, inspected line by line, with its receipt and photos */
    public function store(Request $request): JsonResponse
    {
        if ($denied = $this->deny($request, 'create')) return $denied;

        // Multipart: the lines arrive as JSON text next to the files
        if (is_string($request->input('lines'))) {
            $request->merge(['lines' => json_decode($request->input('lines'), true) ?: []]);
        }

        $data = $request->validate([
            'purchase_order_id'             => ['required', 'integer'],
            'received_date'                 => ['required', 'date', 'before_or_equal:today'],
            'delivery_ref'                  => ['nullable', 'string', 'max:80'],
            'notes'                         => ['nullable', 'string', 'max:2000'],
            'lines'                         => ['required', 'array', 'min:1'],
            'lines.*.item_id'               => ['required', 'integer'],
            'lines.*.delivered_qty'         => ['required', 'integer', 'min:0', 'max:1000000'],
            'lines.*.accepted_qty'          => ['required', 'integer', 'min:0', 'max:1000000'],
            'lines.*.condition'             => ['nullable', Rule::in(PurchaseOrderReceipt::CONDITIONS)],
            'lines.*.checks'                => ['nullable', 'array'],
            'lines.*.checks.*'              => ['string', Rule::in(PurchaseOrderReceipt::CHECKS)],
            'lines.*.reject_reason'         => ['nullable', Rule::in(PurchaseOrderReceipt::REJECT_REASONS)],
            'lines.*.close_short'           => ['nullable', 'boolean'],
            'lines.*.notes'                 => ['nullable', 'string', 'max:500'],
            'lines.*.warehouse_location_id' => ['nullable', 'integer', Rule::exists('warehouse_locations', 'id')->where('owner_id', $this->getOwnerId())],
            'lines.*.expiration_date'       => ['nullable', 'date', 'after:received_date'],
            'lines.*.harvest_date'          => ['nullable', 'date', 'before_or_equal:received_date'],
            'lines.*.freshness_days'        => ['nullable', 'integer', 'min:1', 'max:365'],
            'receipt'                       => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'photos'                        => ['nullable', 'array', 'max:8'],
            'photos.*'                      => ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ], [
            'received_date.before_or_equal' => "The delivery date can't be in the future.",
            'receipt.mimes'                 => 'The delivery receipt can be a photo (JPG, PNG, WebP) or a PDF.',
            'receipt.max'                   => 'The delivery receipt can be up to 10 MB.',
            'photos.max'                    => 'Add up to 8 photos.',
            'photos.*.max'                  => 'Each photo can be up to 8 MB.',
        ]);

        try {
            $receipt = $this->receiving->inspect(
                (int) $data['purchase_order_id'], $data, $this->getOwnerId(),
                $request->file('receipt'), $request->file('photos') ?? [], $request->user(),
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $receipt->load('order:id,order_number,status')], 201);
    }

    /**
     * Warehouse staff (Warehouse) and purchasing staff (Purchase orders) can both receive.
     * view: Warehouse view or Orders view · create: Warehouse create or Orders edit
     */
    private function deny(Request $request, string $need): ?JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof Employee) {
            return null;
        }
        $ok = $need === 'view'
            ? $user->hasModulePermission('warehouse', 'view') || $user->hasModulePermission('sc_orders', 'view')
            : $user->hasModulePermission('warehouse', 'create') || $user->hasModulePermission('sc_orders', 'edit');

        return $ok ? null : response()->json([
            'message' => $need === 'view'
                ? "You need access to the Warehouse or to Purchase orders to see deliveries."
                : "Receiving deliveries needs Warehouse (create) or Purchase orders (edit) access. Ask the shop owner.",
            'code' => 'forbidden_permission',
        ], 403);
    }
}
