<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentGatewaySetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'gateway_name',
        'key_id',
        'key_secret',
        'is_sandbox',
        'is_active',
        'webhook_secret',
        'additional_settings'
    ];

    protected $casts = [
        'is_sandbox' => 'boolean',
        'is_active' => 'boolean',
        'additional_settings' => 'json'
    ];

    protected static function boot()
    {
        parent::boot();

        // When a gateway is activated, deactivate all others
        static::saving(function ($model) {
            if ($model->is_active && $model->isDirty('is_active')) {
                static::where('gateway_name', $model->gateway_name)
                    ->where('id', '!=', $model->id)
                    ->update(['is_active' => false]);
            }
        });
    }

    // Hide sensitive data when converting to array/json
    protected $hidden = [];

    // Get active payment gateway settings
    public static function getActive($gatewayName = null)
    {
        $query = self::where('is_active', true);

        if ($gatewayName) {
            $query->where('gateway_name', $gatewayName);
        }

        $setting = $query->first();
        
        if (!$setting && $gatewayName) {
            // If no active setting found, try to activate the first available one
            $setting = self::where('gateway_name', $gatewayName)->first();
            if ($setting) {
                $setting->update(['is_active' => true]);
            }
        }
        
        return $setting;
    }

    // Helper method to get specific gateway settings
    public static function getGatewaySettings($gatewayName)
    {
        return self::where('gateway_name', $gatewayName)->first();
    }

    // Validate Razorpay key format
    public function validateRazorpayKeys()
    {
        $sandboxKeyPrefix = 'rzp_test_';
        $liveKeyPrefix = 'rzp_live_';
        
        $keyId = $this->key_id;
        $isSandbox = $this->is_sandbox;
        
        // Check if key starts with correct prefix based on sandbox mode
        $expectedPrefix = $isSandbox ? $sandboxKeyPrefix : $liveKeyPrefix;
        $hasCorrectPrefix = str_starts_with($keyId, $expectedPrefix);
        
        if (!$hasCorrectPrefix) {
            \Illuminate\Support\Facades\Log::warning('Razorpay key validation failed', [
                'is_sandbox' => $isSandbox,
                'expected_prefix' => $expectedPrefix,
                'key_starts_with' => substr($keyId, 0, strlen($expectedPrefix)),
                'key_length' => strlen($keyId)
            ]);
            return false;
        }
        
        return true;
    }

    // Get API key based on environment
    public function getKeyId()
    {
        return $this->key_id;
    }

    // Get API secret based on environment
    public function getKeySecret()
    {
        return $this->key_secret;
    }
}
