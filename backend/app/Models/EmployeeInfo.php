<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Helpers\CloudinaryHelper;

class EmployeeInfo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'employees_info';

    protected $fillable = [
        'owner_id',
        'created_by_employee_id',
        'employee_id',
        
        // Basic Information
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'date_of_birth',
        'civil_status',
        'avatar',
        
        // Contact Information
        'personal_email',
        'work_email',
        'mobile_number',
        'address',
        
        // Emergency Contact
        'emergency_contact_name',
        'emergency_contact_number',
        'emergency_relationship',
        
        // Employment Details
        'employment_status',
        'position',
        'department',
        'employment_type',
        'date_hired',
        'work_location',
        'reporting_manager',
        
        // Work Schedule
        'work_schedule',
        'shift_start',
        'shift_end',
        'rest_days',
        
        // Payroll Configuration
        'standard_work_hours_per_day',
        'working_days_per_week',
        'working_days_per_month',
        
        // Payroll Information
        'basic_salary',
        'salary_type',
        'payment_method',
        'bank_name',
        'account_number',
        'tax_status',
        'allowance',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'date_hired' => 'date',
        'basic_salary' => 'decimal:2',
        'standard_work_hours_per_day' => 'decimal:2',
        'working_days_per_week' => 'integer',
        'working_days_per_month' => 'integer',
        'allowance' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'full_name',
        'avatar_url',
    ];

    // Accessor for full_name
    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ]);
        
        return implode(' ', $parts);
    }

    // Accessor for avatar_url
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? CloudinaryHelper::getUrl($this->avatar) : null;
    }


    // Relationships
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(Employee::class, 'created_by_employee_id');
    }

    // Query Scopes
    public function scopeForOwner($query, int $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }

    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('employee_id', 'like', "%{$search}%")
                ->orWhere('work_email', 'like', "%{$search}%")
                ->orWhere('personal_email', 'like', "%{$search}%")
                ->orWhere('mobile_number', 'like', "%{$search}%");
        });
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('employment_status', $status);
    }

    public function scopeByDepartment($query, string $department)
    {
        return $query->where('department', $department);
    }

    public function scopeByLocation($query, string $location)
    {
        return $query->where('work_location', $location);
    }

    /**
     * Check if employee has complete payroll configuration
     */
    public function hasPayrollConfig(): bool
    {
        if (!$this->basic_salary || !$this->salary_type || !$this->standard_work_hours_per_day) {
            return false;
        }

        // For weekly salary, need working_days_per_week
        if ($this->salary_type === 'weekly' && !$this->working_days_per_week) {
            return false;
        }

        // For monthly salary, need working_days_per_month
        if ($this->salary_type === 'monthly' && !$this->working_days_per_month) {
            return false;
        }

        return true;
    }

    /**
     * Get missing payroll configuration fields
     */
    public function getMissingPayrollFields(): array
    {
        $missing = [];

        if (!$this->basic_salary) {
            $missing[] = 'Basic Salary';
        }
        if (!$this->salary_type) {
            $missing[] = 'Salary Type';
        }
        if (!$this->standard_work_hours_per_day) {
            $missing[] = 'Standard Work Hours per Day';
        }
        if ($this->salary_type === 'weekly' && !$this->working_days_per_week) {
            $missing[] = 'Working Days per Week';
        }
        if ($this->salary_type === 'monthly' && !$this->working_days_per_month) {
            $missing[] = 'Working Days per Month';
        }

        return $missing;
    }
}