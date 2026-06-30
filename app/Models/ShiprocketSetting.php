<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiprocketSetting extends Model
{    protected $fillable = [
        'email',
        'password',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];
}
