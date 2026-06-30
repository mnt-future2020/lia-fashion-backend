<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Setting;

class SettingsController extends Controller
{
    public function getCloudinarySettings()
    {
        $settings = [
            'protocol' => config('services.cloudinary.protocol', 'https'),
            'hostname' => config('services.cloudinary.hostname', 'res.cloudinary.com'),
            'port' => config('services.cloudinary.port', ''),
            'path_pattern' => config('services.cloudinary.path_pattern', '/dvdwowdgr/**'),
            'cloud_name' => config('services.cloudinary.cloud_name'),
            'api_key' => config('services.cloudinary.key'),
            'api_secret' => config('services.cloudinary.secret'),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $settings
        ]);
    }

    public function updateCloudinarySettings(Request $request)
    {
        $validated = $request->validate([
            'protocol' => 'required|string',
            'hostname' => 'required|string',
            'port' => 'nullable|string',
            'path_pattern' => 'required|string',
            'cloud_name' => 'required|string',
            'api_key' => 'required|string',
            'api_secret' => 'required|string',
        ]);

        // Update settings in database or config
        foreach ($validated as $key => $value) {
            Setting::updateOrCreate(
                ['key' => 'cloudinary_' . $key],
                ['value' => $value]
            );
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Cloudinary settings updated successfully'
        ]);
    }
} 