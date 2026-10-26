<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EnterpriseInquiry;
use App\Support\PaymentLedger;
use App\Support\VendorDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin → Subscription revenue → Enterprise: the "Talk to us" inquiries */
class EnterpriseInquiryController extends Controller
{
    /** GET /api/admin/subscriptions/enterprise-inquiries?status= */
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $rows = EnterpriseInquiry::query()->with(['vendor:id,name,surname', 'handler:id,name,surname'])
            ->when($status && isset(EnterpriseInquiry::STATUSES[$status]), fn ($q) => $q->where('status', $status))
            ->latest()->orderByDesc('id')->limit(200)->get();
        $shops = VendorDirectory::details($rows->pluck('vendor_id')->filter()->unique()->values()->all());

        return response()->json([
            'success' => true,
            'data' => [
                'inquiries' => $rows->map(fn (EnterpriseInquiry $i) => [
                    'id' => $i->id,
                    'name' => $i->name,
                    'shop_name' => $i->shop_name,
                    'email' => $i->email,
                    'phone' => $i->phone,
                    'branches' => $i->branches,
                    'message' => $i->message,
                    'status' => $i->status,
                    'admin_note' => $i->admin_note,
                    'vendor' => $i->vendor ? ['id' => $i->vendor->id, 'store_name' => $shops[$i->vendor->id]['store_name'] ?? PaymentLedger::personName($i->vendor)] : null,
                    'handled_by' => $i->handler ? PaymentLedger::personName($i->handler) : null,
                    'handled_at' => $i->handled_at?->toIso8601String(),
                    'created_at' => $i->created_at?->toIso8601String(),
                ])->values(),
                'counts' => EnterpriseInquiry::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
                'statuses' => EnterpriseInquiry::STATUSES,
            ],
        ]);
    }

    /** POST /api/admin/subscriptions/enterprise-inquiries/{id} — status and a private note */
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(EnterpriseInquiry::STATUSES))],
            'note' => 'nullable|string|max:1000',
        ]);
        $inquiry = EnterpriseInquiry::query()->find($id);
        if (! $inquiry) {
            return response()->json(['success' => false, 'message' => 'That inquiry no longer exists.'], 404);
        }

        $inquiry->fill([
            'status' => $data['status'],
            'admin_note' => array_key_exists('note', $data) ? ($data['note'] !== null ? trim($data['note']) : null) : $inquiry->admin_note,
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ])->save();

        return response()->json(['success' => true, 'message' => 'Saved.']);
    }
}
