<?php

namespace App\Support;

use App\Helpers\CloudinaryHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Photos attached to a Help & support message (up to 4, JPG/PNG/WebP, 5 MB
 * each; the page shrinks big phone photos before sending). Stored on
 * Cloudinary. A message can be photos only, or words only, or both.
 */
final class SupportAttachments
{
    public const MAX_FILES = 4;

    /** Tests swap the Cloudinary upload for this (receives the file, returns ['secure_url' => ...]) */
    public static ?\Closure $uploader = null;
    public const MAX_KB = 5120;

    /** Validation for a message with optional photos */
    public static function rules(int $minWords = 2): array
    {
        return [
            'message' => ['nullable', 'string', 'max:5000', 'required_without:attachments', "min:{$minWords}"],
            'attachments' => ['nullable', 'array', 'max:' . self::MAX_FILES],
            'attachments.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:' . self::MAX_KB],
        ];
    }

    public const MESSAGES = [
        'message.required_without' => 'Write a message or add a photo.',
        'attachments.max' => 'Add up to ' . self::MAX_FILES . ' photos at a time.',
        'attachments.*.image' => 'Only photos can be attached (JPG, PNG or WebP).',
        'attachments.*.mimes' => 'Only photos can be attached (JPG, PNG or WebP).',
        'attachments.*.max' => 'Each photo can be up to 5 MB.',
    ];

    /**
     * Upload the request's photos. If one fails, nothing is sent and the
     * person is asked to try again, so a photo is never silently dropped.
     *
     * @return array<int, array{url: string, name: string, type: string, size: int}>
     */
    public static function upload(Request $request): array
    {
        $files = array_filter((array) $request->file('attachments', []));
        $saved = [];
        foreach ($files as $file) {
            try {
                $result = self::$uploader ? (self::$uploader)($file) : CloudinaryHelper::upload($file, [
                    'folder' => 'support/attachments/' . date('Y/m'),
                    'resource_type' => 'image',
                ]);
                $saved[] = [
                    'url' => $result['secure_url'],
                    'name' => mb_substr($file->getClientOriginalName(), 0, 120),
                    'type' => $file->getMimeType(),
                    'size' => (int) $file->getSize(),
                ];
            } catch (\Throwable $e) {
                Log::warning('Support photo upload failed: ' . $e->getMessage());
                throw ValidationException::withMessages([
                    'attachments' => ["A photo couldn't be uploaded. Please try again, or send a smaller photo."],
                ]);
            }
        }

        return $saved;
    }

    /** The photos as shown in a conversation */
    public static function present($attachments): array
    {
        return collect(is_array($attachments) ? $attachments : [])
            ->filter(fn ($a) => ! empty($a['url']))
            ->map(fn ($a) => ['url' => $a['url'], 'name' => $a['name'] ?? 'Photo'])
            ->values()->all();
    }
}
