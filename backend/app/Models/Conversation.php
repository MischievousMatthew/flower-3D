<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'customer_id',
        'last_message',
        'last_message_time',
        'unread_count_customer',
        'unread_count_vendor',
        'is_active'
    ];

    protected function casts(): array
    {
        return [
            'last_message_time' => 'datetime',
            'is_active' => 'boolean',
            'unread_count_customer' => 'integer',
            'unread_count_vendor' => 'integer',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->latest();
    }

    public function sharedFiles(): HasMany
    {
        return $this->hasMany(SharedFile::class);
    }

    public function getOtherParticipant(int $currentUserId): ?User
    {
        return $this->vendor_id === $currentUserId 
            ? $this->customer 
            : $this->vendor;
    }

    public function markAsRead(int $userId): void
    {
        if ($this->vendor_id === $userId) {
            $this->update(['unread_count_vendor' => 0]);
        } else {
            $this->update(['unread_count_customer' => 0]);
        }
        
        $this->messages()
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function($q) use ($userId) {
            $q->where('vendor_id', $userId)
              ->orWhere('customer_id', $userId);
        });
    }
}