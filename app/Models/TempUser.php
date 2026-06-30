<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class TempUser extends Model
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'otp',
        'expires_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'password' => 'hashed'
    ];

    protected $hidden = [
        'password',
        'otp'
    ];
}
