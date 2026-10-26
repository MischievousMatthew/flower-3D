<?php

namespace App\Services;

use App\Helpers\CloudinaryHelper;
use Illuminate\Http\UploadedFile;

/**
 * Where delivery receipts and inspection photos are kept (Cloudinary).
 * A class of its own so tests can swap it for one that stores nothing.
 */
class ReceiptFileStore
{
    /** @return array{url: string, public_id: ?string} */
    public function store(UploadedFile $file, string $folder): array
    {
        $result = CloudinaryHelper::upload($file, ['folder' => $folder, 'resource_type' => 'auto']);

        return ['url' => $result['secure_url'], 'public_id' => $result['public_id'] ?? null];
    }

    public function remove(?string $publicId): void
    {
        if ($publicId) {
            CloudinaryHelper::destroy($publicId);
        }
    }
}
