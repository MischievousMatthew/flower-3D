<?php

namespace App\Http\Controllers;

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
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($this->service->listSuppliers($this->getOwnerId(), $filters));
    }

    /** GET /suppliers/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->service->findSupplier($id, $this->getOwnerId()));
    }

    /** POST /suppliers */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_name'   => ['required', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'unique:suppliers,email'],
            'phone'          => ['required', 'string', 'max:20'],
            'address'        => ['required', 'string'],
            'status'         => ['nullable', Rule::in(['active', 'inactive', 'blacklisted'])],
            'logo'           => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ]);

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
        $data = $request->validate([
            'company_name'   => ['sometimes', 'string', 'max:255'],
            'contact_person' => ['sometimes', 'string', 'max:255'],
            'email'          => ['sometimes', 'email', Rule::unique('suppliers', 'email')->ignore($id)],
            'phone'          => ['sometimes', 'string', 'max:20'],
            'address'        => ['sometimes', 'string'],
            'status'         => ['sometimes', Rule::in(['active', 'inactive', 'blacklisted'])],
            'logo'           => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ]);

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
        return response()->json($this->service->activateSupplier($id));
    }

    /** PATCH /suppliers/{id}/deactivate */
    public function deactivate(int $id): JsonResponse
    {
        return response()->json($this->service->deactivateSupplier($id));
    }

    /** PATCH /suppliers/{id}/blacklist */
    public function blacklist(int $id): JsonResponse
    {
        return response()->json($this->service->blacklistSupplier($id));
    }

    /** DELETE /suppliers/{id} */
    public function destroy(int $id): JsonResponse
    {
        $this->service->deleteSupplier($id);

        return response()->json(['message' => 'Supplier deleted successfully.']);
    }

    // ─── Contacts ─────────────────────────────────────────────────────────────

    /** POST /suppliers/{id}/contacts */
    public function storeContact(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'company_name'   => ['required', 'string', 'max:255'],
            'contact_person' => ['required', 'string', 'max:255'],
            'email'          => ['required', 'email', 'unique:supplier_contacts,email'],
            'phone'          => ['required', 'string', 'max:20'],
            'address'        => ['required', 'string'],
            'status'         => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        return response()->json($this->service->addContact($id, $data), 201);
    }

    /** PUT /supplier-contacts/{contactId} */
    public function updateContact(Request $request, int $contactId): JsonResponse
    {
        $data = $request->validate([
            'company_name'   => ['sometimes', 'string', 'max:255'],
            'contact_person' => ['sometimes', 'string', 'max:255'],
            'email'          => ['sometimes', 'email', Rule::unique('supplier_contacts', 'email')->ignore($contactId)],
            'phone'          => ['sometimes', 'string', 'max:20'],
            'address'        => ['sometimes', 'string'],
            'status'         => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        return response()->json($this->service->updateContact($contactId, $data));
    }

    /** DELETE /supplier-contacts/{contactId} */
    public function destroyContact(int $contactId): JsonResponse
    {
        $this->service->removeContact($contactId);

        return response()->json(['message' => 'Contact removed successfully.']);
    }
}
