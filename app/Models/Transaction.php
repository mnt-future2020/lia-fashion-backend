<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'pos_customer_id',
        'payment_transaction_id',
        'invoice_number',
        'order_number',
        'transaction_type',
        'payment_method',
        'payment_status',
        'subtotal_amount',
        'tax_amount',
        'total_amount',
        'transaction_date',
        'customer_name',
        'customer_phone',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'subtotal_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'transaction_date' => 'datetime',
    ];

    /**
     * Get the customer that owns the transaction.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(PosCustomer::class, 'pos_customer_id');
    }

    /**
     * Get the order items for this transaction.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(PosOrderItem::class);
    }

    /**
     * Get transaction items through PosCustomer's order items.
     */
    public function getOrderItems()
    {
        return $this->orderItems()->get();
    }

    /**
     * Get the Razorpay payment transaction associated with this transaction.
     */
    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }

    /**
     * Check if this is a Razorpay transaction
     */
    public function isRazorpayTransaction(): bool
    {
        return $this->payment_method === 'Razorpay' && $this->transaction_type === 'Online';
    }
}
