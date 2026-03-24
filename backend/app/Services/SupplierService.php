<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierContact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SupplierService
{
    // ─── Suppliers ────────────────────────────────────────────────────────────

    /**
     * Paginated, optionally filtered supplier list.
     *
     * @param  array{search?: string, status?: string, per_page?: int}  $filters
     */
    public function listSuppliers(int $ownerId, array $filters = []): LengthAwarePaginator
    {
        return Supplier::query()
            ->where('owner_id', $ownerId)
            ->when(
                isset($filters['search']),
                fn ($q) => $q->where(function ($q) use ($filters) {
                    $q->where('company_name', 'like', "%{$filters['search']}%")
                      ->orWhere('contact_person', 'like', "%{$filters['search']}%")
                      ->orWhere('email', 'like', "%{$filters['search']}%");
                })
            )
            ->when(
                isset($filters['status']),
                fn ($q) => $q->where('status', $filters['status'])
            )
            ->with('contacts')
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Find a single supplier with its contacts and purchase orders.
     */
    public function findSupplier(int $id, int $ownerId): Supplier
    {
        return Supplier::where('owner_id', $ownerId)->with('contacts', 'purchaseOrders')->findOrFail($id);
    }

    /**
     * Create a new supplier, optionally with initial contacts.
     *
     * @param  array{
     *   company_name: string,
     *   contact_person: string,
     *   email: string,
     *   phone: string,
     *   address: string,
     *   status?: string,
     *   contacts?: array<int, array<string, mixed>>
     * }  $data
     */
    public function createSupplier(array $data, int $ownerId): Supplier
    {
        return DB::transaction(function () use ($data, $ownerId) {
            $contacts = $data['contacts'] ?? [];
            unset($data['contacts']);

            $data['owner_id'] = $ownerId;
            $supplier = Supplier::create($data);

            if (!empty($contacts)) {
                $supplier->contacts()->createMany($contacts);
            }

            return $supplier->load('contacts');
        });
    }

    /**
     * Update an existing supplier. Passing a `contacts` key replaces all contacts.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSupplier(int $id, array $data, int $ownerId): Supplier
    {
        return DB::transaction(function () use ($id, $data, $ownerId) {
            $supplier = Supplier::where('owner_id', $ownerId)->findOrFail($id);
            $contacts = $data['contacts'] ?? null;
            unset($data['contacts']);

            $supplier->update($data);

            if ($contacts !== null) {
                $supplier->contacts()->delete();
                $supplier->contacts()->createMany($contacts);
            }

            return $supplier->load('contacts');
        });
    }

    /**
     * Set supplier status to active.
     */
    public function activateSupplier(int $id, int $ownerId): Supplier
    {
        $supplier = Supplier::where('owner_id', $ownerId)->findOrFail($id);
        $supplier->update(['status' => 'active']);
        return $supplier;
    }

    /**
     * Set supplier status to inactive.
     */
    public function deactivateSupplier(int $id, int $ownerId): Supplier
    {
        $supplier = Supplier::where('owner_id', $ownerId)->findOrFail($id);
        $supplier->update(['status' => 'inactive']);
        return $supplier;
    }

    /**
     * Set supplier status to blacklisted.
     */
    public function blacklistSupplier(int $id, int $ownerId): Supplier
    {
        $supplier = Supplier::where('owner_id', $ownerId)->findOrFail($id);
        $supplier->update(['status' => 'blacklisted']);
        return $supplier;
    }

    /**
     * Hard-delete a supplier. Throws if purchase orders exist.
     *
     * @throws \RuntimeException
     */
    public function deleteSupplier(int $id, int $ownerId): void
    {
        $supplier = Supplier::where('owner_id', $ownerId)->withCount('purchaseOrders')->findOrFail($id);

        if ($supplier->purchase_orders_count > 0) {
            throw new \RuntimeException(
                "Cannot delete supplier [{$supplier->company_name}]: linked purchase orders exist."
            );
        }

        $supplier->delete();
    }

    // ─── Contacts ─────────────────────────────────────────────────────────────

    /**
     * Add a single contact to a supplier.
     *
     * @param  array<string, mixed>  $data
     */
    public function addContact(int $supplierId, array $data, int $ownerId): SupplierContact
    {
        return Supplier::where('owner_id', $ownerId)->findOrFail($supplierId)->contacts()->create($data);
    }

    /**
     * Update a single contact.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateContact(int $contactId, array $data, int $ownerId): SupplierContact
    {
        $contact = SupplierContact::whereHas('supplier', fn($q) => $q->where('owner_id', $ownerId))->findOrFail($contactId);
        $contact->update($data);
        return $contact->fresh();
    }

    /**
     * Remove a contact by its ID.
     */
    public function removeContact(int $contactId, int $ownerId): void
    {
        SupplierContact::whereHas('supplier', fn($q) => $q->where('owner_id', $ownerId))->findOrFail($contactId)->delete();
    }
}