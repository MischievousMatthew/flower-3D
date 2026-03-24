<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorApplicationNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_application_id',
        'user_id',
        'note',
        'type'
    ];

    public function application()
    {
        return $this->belongsTo(VendorApplication::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}