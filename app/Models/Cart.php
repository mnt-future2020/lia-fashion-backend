<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = [
        'user_id',
        'total_amount',
        'tax_amount',
        'discount_amount',
        'final_amount',
        'coupon_code'
    ];

    protected $with = ['items.product']; // Eager load relationships

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public function updateTotalAmount()
    {
        $items = $this->items;

        $totalAmount = $items->sum('subtotal');
        $taxAmount = $items->sum('tax_amount');
        $discountAmount = $items->sum('discount_amount');

        $this->total_amount = $totalAmount;
        $this->tax_amount = $taxAmount;
        $this->discount_amount = $discountAmount;
        $this->final_amount = $totalAmount + $taxAmount - $discountAmount;

        $this->save();

        return $this->fresh(); // Return fresh instance with updated values
    }
}
