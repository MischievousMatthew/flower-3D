<?php

namespace App\Support;

use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Support\Collection;

/**
 * Store name and logo for vendor user ids, for admin listings. A vendor's
 * store lives on their (latest) vendor application, matched by email.
 */
final class VendorDirectory
{
    /** @return array<int, array{id:int, name:?string, email:?string, store_name:string, logo_url:?string}> */
    public static function details(iterable $vendorIds): array
    {
        $ids = collect($vendorIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $users = User::query()->whereIn('id', $ids)->get(['id', 'name', 'email']);
        $stores = VendorApplication::query()
            ->whereIn('email', $users->pluck('email'))
            ->orderByDesc('id')
            ->get()
            ->unique('email')
            ->keyBy('email');

        return $users->mapWithKeys(fn ($user) => [$user->id => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'store_name' => $stores[$user->email]->store_name ?? $user->name,
            'logo_url' => $stores[$user->email]->store_logo_url ?? null,
        ]])->all();
    }

    /** One shop's store name (e.g. for "sent to Petal & Stem") */
    public static function nameOf(?int $vendorId): string
    {
        return $vendorId ? (self::details([$vendorId])[$vendorId]['store_name'] ?? 'The shop') : 'The shop';
    }

    public static function unknown($id): array
    {
        return ['id' => $id, 'name' => 'Deleted vendor', 'email' => null, 'store_name' => 'Deleted vendor', 'logo_url' => null];
    }

    /** Vendor user ids whose store name matches a search term */
    public static function idsMatching(string $term): Collection
    {
        $like = '%' . mb_strtolower($term) . '%';
        $emails = VendorApplication::query()->whereRaw('lower(store_name) like ?', [$like])->pluck('email');

        return User::query()->whereIn('email', $emails)->pluck('id');
    }
}
