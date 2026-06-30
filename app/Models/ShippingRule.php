<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'from_weight',
        'to_weight',
        'free_shipping_amount',
        'price',
        'location',
        'shipping_charge',
        'estimated_days',
    ];

    protected $casts = [
        'from_weight' => 'decimal:2',
        'to_weight' => 'decimal:2',
        'free_shipping_amount' => 'decimal:2',
        'price' => 'decimal:2',
        'shipping_charge' => 'decimal:2',
    ];

    public function isWeightBased()
    {
        return $this->type === 'weight';
    }

    public function isLocationBased()
    {
        return $this->type === 'location';
    }
}
