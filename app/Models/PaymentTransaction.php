<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'transaction_id',
        'user_id',
        'razorpay_order_id',
        'razorpay_payment_id',
        'razorpay_signature',
        'status',
        'amount',
        'metadata',
        'currency'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'json'
    ];

    protected $attributes = [
        'currency' => 'INR',
        'status' => 'pending'
    ];

    public const STATUSES = [
        'pending' => 'Pending',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'refunded' => 'Refunded'
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    public function markAsCompleted()
    {
        $this->update(['status' => 'completed']);
    }

    public function markAsFailed()
    {
        $this->update(['status' => 'failed']);
    }

    public function updatePaymentDetails($paymentId, $signature)
    {
        $this->update([
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $signature,
            'status' => 'completed'
        ]);
    }

    public function razorpayOrderItems(): HasMany
    {
        return $this->hasMany(RazorpayOrderItem::class);
    }
}
