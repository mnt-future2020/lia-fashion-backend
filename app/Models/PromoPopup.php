<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromoPopup extends Model
{
    use HasFactory;

    protected $fillable = [
        'enabled',
        'title',
        'message',
        'cta_label',
        'cta_url',
        'image',
        'theme',
        'target_pages',
        'frequency',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'theme' => 'array',
        'target_pages' => 'array',
    ];
}


