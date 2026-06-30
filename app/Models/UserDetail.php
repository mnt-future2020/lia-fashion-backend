<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDetail extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'alt_phone',
        'gender',
        'dob',
        'profile_image',
        'address1',
        'city',
        'district',
        'state',
        'country',
        'pincode'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'dob' => 'date',
    ];

    /**
     * Get the user that owns the details.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
