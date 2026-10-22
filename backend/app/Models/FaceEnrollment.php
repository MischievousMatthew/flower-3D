<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Face descriptors HR captured in person at the shop. They never leave the server. */
class FaceEnrollment extends Model
{
    protected $fillable = ['owner_id', 'employee_info_id', 'descriptors', 'photos', 'enrolled_by'];

    protected $hidden = ['descriptors', 'photos'];

    protected $casts = [
        'descriptors' => 'array',
        'photos' => 'array',
    ];
}
