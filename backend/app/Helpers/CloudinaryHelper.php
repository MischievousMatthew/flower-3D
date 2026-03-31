<?php

namespace App\Helpers;

use Cloudinary\Cloudinary;
use Cloudinary\Configuration\Configuration;

class CloudinaryHelper
{
    private static function getClient(): Cloudinary
    {
        $config = new Configuration();
        $config->cloud->cloudName  = env('CLOUDINARY_CLOUD_NAME');
        $config->cloud->apiKey     = env('CLOUDINARY_API_KEY');
        $config->cloud->apiSecret  = env('CLOUDINARY_API_SECRET');
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