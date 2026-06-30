<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'total_amount',
        'status',
        'payment_id',
        'payment_status',
        'order_type',
        'notes',
        'shipping_address',
        'phone',
        'email',
        'delivered_at',
        'subtotal',
        'tax',
        'shipping_cost',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public static function generateOrderNumber()
    {
        return 'ORD-' . strtoupper(uniqid());
    }
    public static function findOrCreateFromPayment($paymentId, $orderNumber, $data)
{
    return DB::transaction(function() use ($paymentId, $orderNumber, $data) {
        $order = static::where('payment_id', $paymentId)
            ->orWhere('order_number', $orderNumber)
            ->lockForUpdate()
            ->first();

        if ($order) {
            // Update payment status if needed
            if ($order->payment_status !== 'paid') {
                $order->update([
                    'payment_status' => 'paid',
                    'payment_id' => $paymentId
                ]);
            }
            return $order;
        }

        // Create new order
        return static::create($data);
    });
}
}
