<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosCustomer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'phone',
        'email',
        'amount',
        'order_number',
        'invoice_number',
        'payment_method',
        'order_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'order_date' => 'date',
    ];

    /**
     * Get the order items for the customer.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(PosOrderItem::class);
    }

    /**
     * Get the transactions for the customer.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
