<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteReview extends Model
{
    protected $fillable = [
        'name',
        'rating',
        'text',
        'is_approved'
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_approved' => 'boolean',
    ];
}



