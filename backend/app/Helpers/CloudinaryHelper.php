<?php

namespace App\Helpers;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;

class CloudinaryHelper
{
    /**
     * Cloudinary can be configured either via:
     * - CLOUDINARY_CLOUD_NAME + CLOUDINARY_API_KEY + CLOUDINARY_API_SECRET
     * - or a single CLOUDINARY_URL like: cloudinary://<key>:<secret>@<cloud_name>
     *
     * Some hosts (e.g. Render) commonly provide only CLOUDINARY_URL.
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

        $cloudName = $parts['host'] ?? null;
        $apiKey    = $parts['user'] ?? null;
        $apiSecret = $parts['pass'] ?? null;

        return [$cloudName, $apiKey, $apiSecret];
    }

    private static function getClient(): Cloudinary
    {
        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        $apiKey    = env('CLOUDINARY_API_KEY');
        $apiSecret = env('CLOUDINARY_API_SECRET');

        // Fallback to CLOUDINARY_URL if individual vars are not set
        if (empty($cloudName) || empty($apiKey) || empty($apiSecret)) {
            [$urlCloud, $urlKey, $urlSecret] = self::parseCloudinaryUrl(env('CLOUDINARY_URL'));
            $cloudName ??= $urlCloud;
            $apiKey    ??= $urlKey;
            $apiSecret ??= $urlSecret;
        }

        if (empty($cloudName) || empty($apiKey) || empty($apiSecret)) {
            throw new \RuntimeException(
                'Cloudinary is not configured. Set CLOUDINARY_URL or CLOUDINARY_CLOUD_NAME/CLOUDINARY_API_KEY/CLOUDINARY_API_SECRET.'
            );
        }

        $config = new Configuration();
        $config->cloud->cloudName  = $cloudName;
        $config->cloud->apiKey     = $apiKey;
        $config->cloud->apiSecret  = $apiSecret;
        $config->url->secure       = true;

        return new Cloudinary($config);
    }

    public static function upload(string $filePath, array $options = []): array
    {
        $client = self::getClient();
        $result = $client->uploadApi()->upload($filePath, $options);

        return [
            'public_id'   => $result['public_id'],
            'secure_url'  => $result['secure_url'],
        ];
    }

    public static function destroy(string $publicId, array $options = []): void
    {
        try {
            $client = self::getClient();
            $client->uploadApi()->destroy($publicId, $options);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Cloudinary delete failed: ' . $e->getMessage());
        }
    }

    public static function getUrl(string $publicId, string $resourceType = 'image'): string
    {
        if (!$publicId) {
            return '';
        }

        // Return as-is if it's already a full URL
        if (str_starts_with($publicId, 'http')) {
            return $publicId;
        }

        $cloudName = env('CLOUDINARY_CLOUD_NAME');
        
        // Resource types: image, video, raw, auto
        // URL structure: https://res.cloudinary.com/<cloud_name>/<resource_type>/upload/<public_id>
        $type = ($resourceType === 'image') ? 'image' : (($resourceType === 'video') ? 'video' : 'raw');
        
        return "https://res.cloudinary.com/{$cloudName}/{$type}/upload/{$publicId}";
    }
}
