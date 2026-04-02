<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use App\Constants\ErpModule;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Employee extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'owner_id',
        'name',
        'email',
        'username',
        'password',
        'joining_date',
        'status',
        'phone',
        'address',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'joining_date' => 'date',
    ];

    protected $appends = [
        'initials',
        'formatted_joining_date',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function createdEmployeesInfo(): HasMany
    {
        return $this->hasMany(EmployeeInfo::class, 'created_by_employee_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeAssignment::class)->with(['department', 'role.permissions']);
    }

    public function activeAssignments(): HasMany
    {
        return $this->hasMany(EmployeeAssignment::class)
            ->where('is_active', true)
            ->with(['department', 'role.permissions']);
    }

    // ── Module-Based RBAC ─────────────────────────────────────────────────

    public function modulePermissions(): HasMany
    {
        return $this->hasMany(EmployeeModulePermission::class);
    }

    /**
     * Does this employee have any access to the given module?
     */
    public function hasModuleAccess(string $module): bool
    {
        return $this->modulePermissions()->where('module', $module)->exists();
    }

    /**
     * Does this employee have edit access to the given module?
     */
    public function canEditModule(string $module): bool
    {
        return $this->modulePermissions()
            ->where('module', $module)
            ->where('access', 'edit')
            ->exists();
    }

    /**
     * Does this employee have at least view access to the given module?
     */
    public function canViewModule(string $module): bool
    {
        return $this->modulePermissions()
            ->where('module', $module)
            ->whereIn('access', ['view', 'edit'])
            ->exists();
    }

    /**
     * Get all module keys this employee can access.
     */
    public function getAccessibleModules(): array
    {
        return $this->modulePermissions()->pluck('module')->toArray();
    }

    public function primaryAssignment(): HasOne
    {
        return $this->hasOne(EmployeeAssignment::class)
            ->where('is_primary', true)
            ->where('is_active', true);
    }

    // ── Permission Helpers ────────────────────────────────────────────────

    public function hasAssignment(int $assignmentId): bool
    {
        return $this->activeAssignments()->where('id', $assignmentId)->exists();
    }

    // All permission slugs across ALL active assignments (for broadest check)
    public function getAllPermissions(): Collection
    {
        return $this->activeAssignments
            ->flatMap(fn ($a) => $a->role->permissions->pluck('slug'))
            ->unique();
    }

    // Permission check for a SPECIFIC assignment context
    public function canInContext(int $assignmentId, string $permissionSlug): bool
    {
        $assignment = $this->activeAssignments->firstWhere('id', $assignmentId);
        return $assignment?->role->hasPermission($permissionSlug) ?? false;
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeByOwner($query, int $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function scopeOnLeave($query)
    {
        return $query->where('status', 'On Leave');
    }

    public function scopeResigned($query)
    {
        return $query->where('status', 'Resign');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('username', 'like', "%{$search}%");
        });
    }

    // ── Accessors ─────────────────────────────────────────────────────────

    public function getInitialsAttribute(): string
    {
        if (empty($this->name)) {
            return '';
        }

        return strtoupper(implode('', array_map(
            fn ($word) => $word[0] ?? '',
            explode(' ', $this->name)
        )));
    }

    public function getFormattedJoiningDateAttribute(): ?string
    {
        return $this->joining_date?->format('d-m-y');
    }
}