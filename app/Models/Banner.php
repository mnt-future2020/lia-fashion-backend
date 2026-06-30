<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'image',
        'status',
        'sort_order'
    ];

    protected $casts = [
        'status' => 'boolean',
    ];
}
