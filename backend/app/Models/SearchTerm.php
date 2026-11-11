<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A word or phrase customers searched in the shop, and how often (no user link) */
class SearchTerm extends Model
{
    protected $fillable = ['term', 'hits', 'last_searched_at'];

    protected $casts = [
        'hits' => 'integer',
        'last_searched_at' => 'datetime',
    ];
}
