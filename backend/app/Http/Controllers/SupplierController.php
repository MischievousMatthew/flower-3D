<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

use App\Traits\ScopesOwner;
 
use App\Helpers\CloudinaryHelper;
 
class SupplierController extends Controller
{
    use ScopesOwner;
 
    public function __construct(private readonly SupplierService $service) {}





    /** GET /suppliers */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'search'   => ['nullable', 'string', 'max:100'],
            'status'   => ['nullable', 'string', Rule::in(['active', 'inactive', 'blacklisted'])],
            'type'     => ['nullable', 'string', Rule::in([...Supplier::TYPES, 'none'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        // The paginator's own fields stay as they were; counts are added beside them
        return response()->json([
            ...$this->service->listSuppliers($this->getOwnerId(), $filters)->toArray(),
            'counts' => $this->service->supplierCounts($this->getOwnerId()),
        ]);
    }

    /** GET /suppliers/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->service->findSupplier($id, $this->getOwnerId()));
    }

    /** POST /suppliers */
    public function store(Request $request): JsonResponse
    {
        $this->decodeJsonFields($request);
        $data = $request->validate([
            'company_name'   => ['required', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'unique:suppliers,email'],
            'phone'          => ['required', 'string', 'max:20'],
            'address'        => ['required', 'string'],
            'status'         => ['nullable', Rule::in(['active', 'inactive', 'blacklisted'])],
            'logo'           => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            ...$this->profileRules(),
        ]);
        $data = $this->withContactDefaults($data);

        $uploadedLogoId = null;

        try {
            if ($request->hasFile('logo')) {
                $result = CloudinaryHelper::uploadImage(
                    $request->file('logo'),
                    'supplier-logos'
                );
                $uploadedLogoId = $result['public_id'];
                $data['logo'] = $uploadedLogoId;
            }

            return response()->json($this->service->createSupplier($data, $this->getOwnerId()), 201);
        } catch (\Throwable $e) {
            if ($uploadedLogoId) {
                CloudinaryHelper::destroy($uploadedLogoId);
            }

            Log::error('Supplier create failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'has_logo' => $request->hasFile('logo'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create supplier',
            ], 500);
        }
    }

    /** PUT /suppliers/{id} */
    public function update(Request $request, int $id): JsonResponse
    {
        $this->decodeJsonFields($request);
        $data = $request->validate([
            'company_name'   => ['sometimes', 'string', 'max:255'],
            'contact_person' => ['sometimes', 'string', 'max:255'],
            'email'          => ['sometimes', 'email', Rule::unique('suppliers', 'email')->ignore($id)],
            'phone'          => ['sometimes', 'string', 'max:20'],
            'address'        => ['sometimes', 'string'],
            'status'         => ['sometimes', Rule::in(['active', 'inactive', 'blacklisted'])],
            'logo'           => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            ...$this->profileRules(),
        ]);
        $data = $this->withContactDefaults($data);

        $uploadedLogoId = null;

        try {
            $previousLogoId = null;

            if ($request->hasFile('logo')) {
                $supplier = $this->service->findSupplier($id, $this->getOwnerId());
                $previousLogoId = $supplier->logo;
                $result = CloudinaryHelper::uploadImage(
                    $request->file('logo'),
                    'supplier-logos'
                );
                $uploadedLogoId = $result['public_id'];
                $data['logo'] = $uploadedLogoId;
            }

            $supplier = $this->service->updateSupplier($id, $data, $this->getOwnerId());

            if (
                $uploadedLogoId &&
                $previousLogoId &&
                !str_starts_with($previousLogoId, 'http') &&
                $previousLogoId !== $uploadedLogoId
            ) {
                CloudinaryHelper::destroy($previousLogoId);
            }

            return response()->json($supplier);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            if ($uploadedLogoId) {
                CloudinaryHelper::destroy($uploadedLogoId);
            }

            return response()->json([
                'success' => false,
                'message' => 'Supplier not found',
            ], 404);
        } catch (\Throwable $e) {
            if ($uploadedLogoId) {
                CloudinaryHelper::destroy($uploadedLogoId);
            }

            Log::error('Supplier update failed', [
                'supplier_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'has_logo' => $request->hasFile('logo'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update supplier',
            ], 500);
        }
    }

    /** PATCH /suppliers/{id}/activate */
    public function activate(int $id): JsonResponse
    {
        return response()->json($this->service->activateSupplier($id, $this->getOwnerId()));
    }

    /** PATCH /suppliers/{id}/deactivate */
    public function deactivate(int $id): JsonResponse
    {
        return response()->json($this->service->deactivateSupplier($id, $this->getOwnerId()));
    }

    /** PATCH /suppliers/{id}/blacklist */
    public function blacklist(int $id): JsonResponse
    {
        return response()->json($this->service->blacklistSupplier($id, $this->getOwnerId()));
    }

    /** DELETE /suppliers/{id} */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->deleteSupplier($id, $this->getOwnerId());
        } catch (\RuntimeException $e) {
            // Has purchase orders: keep the history, suggest the other options
            return response()->json([
                'message' => "This supplier has purchase orders, so it can't be deleted. Deactivate or blacklist it instead to keep its history.",
            ], 422);
        }

        return response()->json(['message' => 'Supplier deleted successfully.']);
    }

    // ─── Profile fields ───────────────────────────────────────────────────────

    /** Fields the supplier forms send as JSON text inside multipart uploads */
    private function decodeJsonFields(Request $request): void
    {
        // The forms send type, schedule and terms together as `profile`, so a
        // field cleared on the form still reaches the server (as null)
        $profile = $request->input('profile');
        if (is_string($profile) && is_array($decoded = json_decode($profile, true))) {
            $request->merge(array_intersect_key($decoded, array_flip(['supplier_type', 'delivery_days', 'lead_time_days', 'order_cutoff', 'minimum_order', 'payment_terms', 'notes'])));
        }

        foreach (['contacts', 'delivery_days'] as $field) {
            $value = $request->input($field);
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                $request->merge([$field => is_array($decoded) ? $decoded : []]);
            }
        }
        // Empty optional fields arrive as "" from FormData
        foreach (['supplier_type', 'lead_time_days', 'order_cutoff', 'minimum_order', 'payment_terms', 'notes'] as $field) {
            if ($request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }
    }

    /** Type, delivery schedule, terms and additional contacts */
    private function profileRules(): array
    {
        return [
            'supplier_type'             => ['nullable', Rule::in(Supplier::TYPES)],
            'delivery_days'             => ['nullable', 'array'],
            'delivery_days.*'           => ['string', Rule::in(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])],
            'lead_time_days'            => ['nullable', 'integer', 'min:0', 'max:180'],
            'order_cutoff'              => ['nullable', 'date_format:H:i'],
            'minimum_order'             => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'payment_terms'             => ['nullable', 'string', 'max:60'],
            'notes'                     => ['nullable', 'string', 'max:2000'],
            'contacts'                  => ['sometimes', 'array', 'max:20'],
            'contacts.*.contact_person' => ['required', 'string', 'max:255'],
            'contacts.*.email'          => ['required', 'email', 'distinct'],
            'contacts.*.phone'          => ['required', 'string', 'max:20'],
            'contacts.*.company_name'   => ['nullable', 'string', 'max:255'],
            'contacts.*.address'        => ['nullable', 'string'],
            'contacts.*.status'         => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }

    /** A contact without its own company or address uses the supplier's */
    private function withContactDefaults(array $data): array
    {
        if (! isset($data['contacts'])) {
            return $data;
        }
        $data['contacts'] = array_map(fn ($c) => [
            'contact_person' => $c['contact_person'],
            'email'          => $c['email'],
            'phone'          => $c['phone'],
            'company_name'   => ($c['company_name'] ?? '') ?: ($data['company_name'] ?? ''),
            'address'        => ($c['address'] ?? '') ?: ($data['address'] ?? ''),
            'status'         => $c['status'] ?? 'active',
        ], $data['contacts']);

        return $data;
    }

    // ─── Contacts ─────────────────────────────────────────────────────────────

    /** GET /suppliers/{id}/contacts */
    public function contacts(int $supplierId): JsonResponse
    {
        return response()->json($this->service->findSupplier($supplierId, $this->getOwnerId())->contacts);
    }

    /** POST /suppliers/{id}/contacts */
    public function storeContact(Request $request, int $supplierId): JsonResponse
    {
        $data = $request->validate([
            'company_name'   => ['required', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'unique:supplier_contacts,email'],
            'phone'          => ['required', 'string', 'max:20'],
            'address'        => ['required', 'string'],
            'status'         => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        return response()->json($this->service->addContact($supplierId, $data, $this->getOwnerId()), 201);
    }

    /** PUT /suppliers/{supplierId}/contacts/{id} */
    public function updateContact(Request $request, int $supplierId, int $id): JsonResponse
    {
        $data = $request->validate([
            'company_name'   => ['sometimes', 'string', 'max:255'],
            'contact_person' => ['sometimes', 'string', 'max:255'],
            'email'          => ['sometimes', 'email', Rule::unique('supplier_contacts', 'email')->ignore($id)],
            'phone'          => ['sometimes', 'string', 'max:20'],
            'address'        => ['sometimes', 'string'],
            'status'         => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        return response()->json($this->service->updateContact($id, $data, $this->getOwnerId()));
    }

    /** DELETE /suppliers/{supplierId}/contacts/{id} */
    public function destroyContact(int $supplierId, int $id): JsonResponse
    {
        $this->service->removeContact($id, $this->getOwnerId());

        return response()->json(['message' => 'Contact removed successfully.']);
    }
}
