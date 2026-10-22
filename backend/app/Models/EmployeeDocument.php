<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A file on an HR profile. Files are private on Cloudinary; read them through a short-lived signed link. */
class EmployeeDocument extends Model
{
    use BelongsToOwner;

    public const CATEGORIES = ['Contract', 'Government ID', 'Resume', 'Certificate', 'Clearance', 'Medical', 'Other'];

    protected $fillable = [
        'owner_id',
        'employee_info_id',
        'category',
        'title',
        'file_name',
        'public_id',
        'resource_type',
        'format',
        'size',
        'expires_on',
        'uploaded_by',
    ];

    protected $hidden = ['public_id'];

    protected $casts = [
        'expires_on' => 'date',
        'size' => 'integer',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(EmployeeInfo::class, 'employee_info_id');
    }
}
