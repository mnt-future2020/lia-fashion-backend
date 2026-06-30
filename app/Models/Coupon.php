<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'discount_value',
        'discount_type', // 'amount' or 'percentage'
        'min_order_value',
        'min_purchase_limit',
        'start_date',
        'end_date',
        'is_active',
        'usage_count',
        'max_usage',
        'redemption_status' // 'unused', 'used' - tracks actual usage status
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
        'discount_value' => 'float',
        'min_order_value' => 'integer', // Changed from float to integer
        'min_purchase_limit' => 'integer',
        'usage_count' => 'integer',
        'max_usage' => 'integer',
        'redemption_status' => 'string'
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'coupon_usages')
                    ->withTimestamps();
    }

    public function hasBeenUsedByUser($userId)
    {
        return $this->users()->where('user_id', $userId)->exists();
    }
}
