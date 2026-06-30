<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceSetting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'prefix',
        'financial_year_start',
        'financial_year_end',
        'last_invoice_number',
        'last_sequence_number',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'financial_year_start' => 'date',
        'financial_year_end' => 'date',
        'last_sequence_number' => 'integer',
        'is_active' => 'boolean',
    ];
}
