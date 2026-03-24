<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id', 'name', 'slug',
        'hierarchy_level', 'accessible_modules',
    ];

    protected $casts = ['accessible_modules' => 'array'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    // Convenience: does this role have a given permission slug?
    public function hasPermission(string $slug): bool
    {
        return $this->permissions->pluck('slug')->contains($slug);
    }
}
