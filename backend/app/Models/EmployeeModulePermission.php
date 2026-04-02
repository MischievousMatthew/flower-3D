<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeModulePermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'module',
        'access',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Does this permission grant edit-level access?
     */
    public function isEdit(): bool
    {
        return $this->access === 'edit';
    }

    /**
     * Does this permission grant at least view-level access?
     */
    public function isView(): bool
    {
        return in_array($this->access, ['view', 'edit'], true);
    }
}
