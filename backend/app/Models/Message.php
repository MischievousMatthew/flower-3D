<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class Message extends Model
{
    use BelongsToOwner, HasFactory;

    protected $fillable = [
        'owner_id',
        'conversation_id',
        'sender_id',
        'message',
        'message_type',
        'attachments',
        'is_read',
        'read_at'
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'read_at' => 'datetime',
            'attachments' => 'array', // ADD THIS LINE
        ];
    }

    // Accessor for attachments (already works with the 'array' cast)
    // But let's add a custom one for safety
    public function getAttachmentsAttribute($value)
    {
        if (empty($value)) {
            return [];
        }
        
        // If already an array, return it
        if (is_array($value)) {
            return $value;
        }
        
        // Try to decode JSON
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            return $decoded ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to decode attachments JSON', [
                'value' => $value,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    // Mutator for attachments
    public function setAttachmentsAttribute($value)
    {
        if (empty($value)) {
            $this->attributes['attachments'] = null;
        } elseif (is_array($value)) {
            $this->attributes['attachments'] = json_encode($value, JSON_UNESCAPED_SLASHES);
        } elseif (is_string($value) && json_decode($value) !== null) {
            // Already JSON string
            $this->attributes['attachments'] = $value;
        } else {
            $this->attributes['attachments'] = json_encode([$value], JSON_UNESCAPED_SLASHES);
        }
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    protected function getFormattedTimeAttribute(): string
    {
        return match (true) {
            $this->created_at->isToday() => $this->created_at->format('h:i A'),
            $this->created_at->isYesterday() => 'Yesterday, ' . $this->created_at->format('h:i A'),
            default => $this->created_at->format('M d, h:i A'),
        };
    }

    protected function getIsFromVendorAttribute(): bool
    {
        return $this->sender->isVendor();
    }

    public function markAsRead(): void
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now()
            ]);
        }
    }

    public function getAttachmentUrls(): array
    {
        // Use the accessor instead
        $attachments = $this->attachments;
        
        if (empty($attachments)) {
            return [];
        }

        return collect($attachments)
            ->map(fn($attachment) => [
                'url' => $attachment['url'] ?? null,
                'name' => $attachment['name'] ?? 'Attachment',
                'type' => $attachment['type'] ?? 'unknown',
                'size' => $attachment['size'] ?? 0,
            ])
            ->toArray();
    }
}
