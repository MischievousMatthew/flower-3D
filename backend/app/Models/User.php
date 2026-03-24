<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_VENDOR = 'vendor';
    public const ROLE_CUSTOMER = 'customer';

    protected $fillable = [
        'name',
        'surname',
        'username',
        'email',
        'password',
        'contact_number',
        'role',
        'is_verified',
        'vendor_data',
        'date_of_birth',
        'gender',
        'nationality',
        'address',
        'city',
        'postal_code',
        'profile_picture',
        'plan',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_verified' => 'boolean',
        'vendor_data' => 'array',
        'date_of_birth' => 'date',
    ];

    // Add these accessors
    protected function getFullNameAttribute(): string
    {
        return $this->name . ' ' . $this->surname;
    }

    protected function getAvatarUrlAttribute(): string
    {
        if ($this->profile_picture) {
            return asset('storage/' . $this->profile_picture);
        }
        
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->full_name) . 
               '&background=7F9CF5&color=ffffff&size=128';
    }

    protected function getIsOnlineAttribute(): bool
    {
        // Simple online check - you can implement more sophisticated logic
        return true; // For now, return true. You can implement actual online status later
    }

    // Relationships
    public function vendorConversations()
    {
        return $this->hasMany(Conversation::class, 'vendor_id');
    }

    public function customerConversations()
    {
        return $this->hasMany(Conversation::class, 'customer_id');
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function sharedFiles()
    {
        return $this->hasMany(SharedFile::class, 'uploaded_by');
    }

    public function vendorApplication()
    {
        return $this->hasOne(VendorApplication::class, 'email', 'email');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'owner_id');
    }

    /**
     * Get all employee info records owned by this user.
     */
    public function employeesInfo(): HasMany
    {
        return $this->hasMany(EmployeeInfo::class, 'owner_id');
    }

    public function isVendorOwner(): bool
    {
        return $this->role === 'vendor_owner'; // Adjust based on your role system
    }

    // Role methods
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isVendor(): bool
    {
        return $this->role === self::ROLE_VENDOR;
    }

    public function isCustomer(): bool
    {
        return $this->role === self::ROLE_CUSTOMER;
    }
}