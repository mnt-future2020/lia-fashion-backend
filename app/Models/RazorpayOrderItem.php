<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RazorpayOrderItem extends Model
{
    protected $fillable = [
        'payment_transaction_id',
        'product_id',
        'product_name',
        'quantity',
        'unit_price',
        'total_price',
        'color',
        'size',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
