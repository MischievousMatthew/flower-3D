<?php

namespace App\Helpers;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class CloudinaryHelper
{
    /**
     * Cloudinary can be configured either via:
     * - CLOUDINARY_CLOUD_NAME + CLOUDINARY_API_KEY + CLOUDINARY_API_SECRET
     * - or a single CLOUDINARY_URL like: cloudinary://<key>:<secret>@<cloud_name>
     */
    private static function parseCloudinaryUrl(?string $cloudinaryUrl): array
    {
        if (!$cloudinaryUrl) return [null, null, null];

        $cloudinaryUrl = trim($cloudinaryUrl);

        // Some users accidentally paste "CLOUDINARY_URL=cloudinary://..."
        if (str_starts_with($cloudinaryUrl, 'CLOUDINARY_URL=')) {
            $cloudinaryUrl = substr($cloudinaryUrl, strlen('CLOUDINARY_URL='));
        }

        $parts = parse_url($cloudinaryUrl);
        if (!$parts) return [null, null, null];

        return [
            $parts['host'] ?? null,
            $parts['user'] ?? null,
            $parts['pass'] ?? null,
        ];
    }

    private static function getClient(): Cloudinary
    {
        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        $apiKey    = env('CLOUDINARY_API_KEY');
        $apiSecret = env('CLOUDINARY_API_SECRET');

        // Fallback to CLOUDINARY_URL if individual vars are not set
        if (empty($cloudName) || empty($apiKey) || empty($apiSecret)) {
            [$urlCloud, $urlKey, $urlSecret] = self::parseCloudinaryUrl(env('CLOUDINARY_URL'));
            $cloudName = $cloudName ?: $urlCloud;
            $apiKey    = $apiKey    ?: $urlKey;
            $apiSecret = $apiSecret ?: $urlSecret;
        }

        if (empty($cloudName) || empty($apiKey) || empty($apiSecret)) {
            throw new \RuntimeException(
                'Cloudinary is not configured. Set CLOUDINARY_URL or '
                . 'CLOUDINARY_CLOUD_NAME/CLOUDINARY_API_KEY/CLOUDINARY_API_SECRET.'
            );
        }

        $config = new Configuration();
        $config->cloud->cloudName = $cloudName;
        $config->cloud->apiKey    = $apiKey;
        $config->cloud->apiSecret = $apiSecret;
        $config->url->secure      = true;

        return new Cloudinary($config);
    }

    /**
     * Upload a file to Cloudinary.
     *
     * Accepts either:
     *   - A file path string
     *   - An \Illuminate\Http\UploadedFile instance
     *
     * getRealPath() can return false on some server environments (e.g. Render).
     * We copy to a guaranteed temp path as a safe fallback.
     *
     * @param  string|\Illuminate\Http\UploadedFile  $file
     * @param  array  $options  Cloudinary upload options
     * @return array{ public_id: string, secure_url: string }
     * @throws \RuntimeException on upload failure
     */
    public static function upload($file, array $options = []): array
    {
        $tmpPath = null;

        // ── Resolve file path safely ──────────────────────────────────────
        if ($file instanceof \Illuminate\Http\UploadedFile) {
            $filePath = $file->getRealPath();

            if (!$filePath || !file_exists($filePath)) {
                // Fallback: move to a known temp directory
                $tmpPath  = tempnam(sys_get_temp_dir(), 'cld_upload_');
                $file->move(dirname($tmpPath), basename($tmpPath));
                $filePath = $tmpPath;
            }
        } else {
            $filePath = $file;
        }

        if (!file_exists($filePath)) {
            throw new \RuntimeException("Upload file not found at path: {$filePath}");
        }

        // ── Default resource_type to auto if not specified ────────────────
        if (!isset($options['resource_type'])) {
            $options['resource_type'] = 'auto';
        }

        try {
            $client = self::getClient();
            $result = $client->uploadApi()->upload($filePath, $options);

            // SDK v2 returns ApiResponse which implements ArrayAccess.
            // Convert to plain array to avoid edge cases.
            $data = is_array($result) ? $result : (array) $result;

            $publicId  = $data['public_id']  ?? null;
            $secureUrl = $data['secure_url'] ?? null;

            if (!$publicId || !$secureUrl) {
                Log::error('Cloudinary upload returned incomplete response', [
                    'response_keys' => array_keys($data),
                    'options'       => $options,
                ]);
                throw new \RuntimeException(
                    'Cloudinary upload succeeded but returned no public_id or secure_url. '
                    . 'Response keys: ' . implode(', ', array_keys($data))
                );
            }

            Log::info('Cloudinary upload successful', [
                'public_id'  => $publicId,
                'secure_url' => substr($secureUrl, 0, 60) . '...',
            ]);

            return [
                'public_id'  => $publicId,
                'secure_url' => $secureUrl,
            ];

        } catch (\Cloudinary\Exception\Error $e) {
            Log::error('Cloudinary API error during upload', [
                'message' => $e->getMessage(),
                'options' => $options,
                'file'    => $filePath,
            ]);
            throw new \RuntimeException('Cloudinary upload failed: ' . $e->getMessage(), 0, $e);

        } finally {
            // Clean up temp file if we created one
            if ($tmpPath && file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    /**
     * Upload an image file using the safer UploadedFile path handling.
     */
    public static function uploadImage(
        ?UploadedFile $file,
        string $folder,
        array $options = []
    ): ?array {
        if (!$file) {
            return null;
        }

        if (!$file->isValid()) {
            throw new \RuntimeException('Uploaded image is invalid.');
        }

        $result = self::upload($file, array_merge([
            'folder' => $folder,
            'resource_type' => 'image',
        ], $options));

        return $result;
    }

    /**
     * Delete a file from Cloudinary by public_id.
     * Never throws — failures are logged and swallowed so they
     * never block the main operation.
     */
    public static function destroy(string $publicId, array $options = []): void
    {
        if (!$publicId) return;

        try {
            $client = self::getClient();
            $client->uploadApi()->destroy($publicId, $options);

            Log::info('Cloudinary file deleted', ['public_id' => $publicId]);

        } catch (\Throwable $e) {
            Log::warning('Cloudinary delete failed (non-critical)', [
                'public_id' => $publicId,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build a Cloudinary delivery URL from a public_id.
     *
     * If the value is already a full URL (e.g. old local storage URLs
     * or directly stored Cloudinary secure URLs), return it as-is.
     *
     * @param  string  $publicId       Cloudinary public_id or full URL
     * @param  string  $resourceType   image | video | raw
     */
    public static function getUrl(string $publicId, string $resourceType = 'image'): string
    {
        if (!$publicId) {
            return '';
        }

        // Already a full URL — return as-is
        if (str_starts_with($publicId, 'http')) {
            return $publicId;
        }

        $cloudName = env('CLOUDINARY_CLOUD_NAME');

        if (!$cloudName) {
            // Fallback: try to parse from CLOUDINARY_URL
            [$cloudName] = self::parseCloudinaryUrl(env('CLOUDINARY_URL'));
        }

        if (!$cloudName) {
            Log::warning('CLOUDINARY_CLOUD_NAME not set, cannot build URL for: ' . $publicId);
            return '';
        }

        $type = match ($resourceType) {
            'video' => 'video',
            'raw'   => 'raw',
            default => 'image',
        };

        return "https://res.cloudinary.com/{$cloudName}/{$type}/upload/{$publicId}";
    }

    /**
     * Build a Cloudinary raw resource URL (for 3D models, PDFs, etc.)
     */
    public static function getRawUrl(string $publicId): string
    {
        return self::getUrl($publicId, 'raw');
    }

    /**
     * Quick connectivity test — useful for debug endpoints.
     * Returns ['connected' => true/false, ...]
     */
    public static function testConnection(): array
    {
        try {
            $client = self::getClient();
            // Ping by listing a single resource — minimal API call
            $client->adminApi()->assets(['max_results' => 1]);

            return [
                'connected'  => true,
                'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
            ];
        } catch (\Throwable $e) {
            return [
                'connected' => false,
                'error'     => $e->getMessage(),
            ];
        }
    }
}
