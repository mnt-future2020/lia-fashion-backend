<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = Setting::whereIn('key', [
            'cloudinary_cloud_name',
            'cloudinary_api_key',
            'cloudinary_api_secret'
        ])->get();

        return response()->json($settings);
    }

    public function store(Request $request)
    {
        $request->validate([
            'cloudinary_cloud_name' => 'required|string',
            'cloudinary_api_key' => 'required|string',
            'cloudinary_api_secret' => 'required|string',
        ]);

        foreach ($request->all() as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        return response()->json(['message' => 'Settings updated successfully']);
    }
}
