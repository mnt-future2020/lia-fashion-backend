<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseEntry extends Model
{
    protected $table = 'purchase_entries';

    protected $fillable = [
        'vendor_id',
        'purchase_no',
        'purchase_date',
        'product_name',
        'quantity',
        'unit_price',
        'total_cost',
        'discount',
        'payment_status',
        'amount_paid',
        'amount_due',
        'notes',
        'status',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseEntryItem::class);
    }
}
