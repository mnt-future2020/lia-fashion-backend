<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'logo',
        'store_name',
        'gst_no',
        'contact_first_name',
        'contact_last_name',
        'mobile_no',
        'landline_no',
        'email',
        'door_no',
        'street_name',
        'pin_code',
        'district',
        'state',
        'country',
    ];
}
