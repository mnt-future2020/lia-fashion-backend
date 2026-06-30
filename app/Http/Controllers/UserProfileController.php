<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserDetail;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;

class UserProfileController extends Controller
{
    protected $cloudinary;

    public function __construct()
    {
        $cloudSettings = [
            'cloud_name' => Setting::getValue('cloudinary_cloud_name'),
            'api_key' => Setting::getValue('cloudinary_api_key'),
            'api_secret' => Setting::getValue('cloudinary_api_secret')
        ];

        Configuration::instance([
            'cloud' => $cloudSettings
        ]);
        $this->cloudinary = new UploadApi();
    }

    public function getProfile(Request $request)
    {
        $user = $request->user();
        $user->load('details');

        return response()->json($user);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20|unique:users,phone,' . $user->id,
            'alt_phone' => 'sometimes|string|max:20|unique:user_details,alt_phone,' . $user->id . ',user_id',
            'gender' => 'sometimes|in:male,female,other',
            'dob' => 'sometimes|date',
            'profile_image' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
            'address1' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'district' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'country' => 'required|string|max:100',
            'pincode' => 'required|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $userDetails = $user->details()->first();
            $updateData = $request->only([
                'alt_phone',
                'gender',
                'dob',
                'address1',
                'city',
                'district',
                'state',
                'country',
                'pincode'
            ]);

            // Handle profile image upload
            if ($request->hasFile('profile_image')) {
                $file = $request->file('profile_image');

                if ($file->isValid()) {
                    // Delete old image if exists
                    if ($userDetails && $userDetails->profile_image) {
                        $this->deleteCloudinaryImage($userDetails->profile_image);
                    }

                    // Upload new image to Cloudinary
                    $result = $this->cloudinary->upload(
                        $file->getRealPath(),
                        [
                            'folder' => 'profiles',
                            'resource_type' => 'image'
                        ]
                    );

                    // Add Cloudinary URL to update data
                    $updateData['profile_image'] = $result['secure_url'];
                }
            }

            // Update user details
            $userDetails = $user->details()->updateOrCreate(
                ['user_id' => $user->id],
                $updateData
            );

            // Update user name and phone if provided
            $userUpdated = false;
            if ($request->has('name')) {
                $user->name = $request->name;
                $userUpdated = true;
            }
            if ($request->has('phone')) {
                $user->phone = $request->phone;
                $userUpdated = true;
            }
            
            if ($userUpdated) {
                $user->save();
            }

            // Reload the user with details
            $user->load('details');

            return response()->json($user);
        } catch (\Exception $e) {
            \Log::error('Profile update error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update profile: ' . $e->getMessage()], 500);
        }
    }

    private function deleteCloudinaryImage($url)
    {
        try {
            if (!$url) return;

            // Extract public_id from URL
            preg_match('/profiles\/[^.]+/', $url, $matches);
            if (isset($matches[0])) {
                $this->cloudinary->destroy($matches[0]);
            }
        } catch (\Exception $e) {
            // Log error but don't stop execution
            \Log::error('Failed to delete Cloudinary image: ' . $e->getMessage());
        }
    }
}
