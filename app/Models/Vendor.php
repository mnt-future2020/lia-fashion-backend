<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'vendor_name',
        'contact_person_name',
        'gst_number',
        'email',
        'phone_number',
        'category',
        'address_line1',
        'city',
        'district',
        'state',
        'country',
        'pincode',
        'status',
        'total_orders',
        'total_amount',
        'last_purchase_date'
    ];

    protected $casts = [
        'total_orders' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'last_purchase_date' => 'datetime'
    ];

    // Scopes for filtering
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('vendor_name', 'like', "%{$search}%")
              ->orWhere('contact_person_name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('phone_number', 'like', "%{$search}%")
              ->orWhere('gst_number', 'like', "%{$search}%")
              ->orWhere('category', 'like', "%{$search}%");
        });
    }

    public function purchaseEntries()
    {
        return $this->hasMany(\App\Models\PurchaseEntry::class);
    }
}
