<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;

class CompanyController extends Controller
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

    /**
     * Get company details
     */
    public function index()
    {
        $company = Company::first();
        return response()->json($company);
    }

    /**
     * Create or update company details
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'store_name' => 'required|string|max:255',
            'gst_no' => 'nullable|string|max:255',
            'contact_first_name' => 'required|string|max:255',
            'contact_last_name' => 'required|string|max:255',
            'mobile_no' => 'required|string|max:20',
            'landline_no' => 'nullable|string|max:20',
            'email' => 'required|email|max:255',
            'door_no' => 'required|string|max:255',
            'street_name' => 'required|string|max:255',
            'pin_code' => 'required|string|max:20',
            'district' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'country' => 'required|string|max:255',
            'logo' => 'nullable|file|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->except('logo');
        $company = Company::first();

        // Handle logo upload
        if ($request->hasFile('logo')) {
            try {
                // Delete old logo if exists
                if ($company && $company->logo) {
                    $this->deleteCloudinaryImage($company->logo);
                }

                // Upload new logo to Cloudinary
                $result = $this->cloudinary->upload(
                    $request->file('logo')->getRealPath(),
                    [
                        'folder' => 'company',
                        'resource_type' => 'image',
                        'transformation' => [
                            'width' => 500,
                            'height' => 500,
                            'crop' => 'fill',
                            'quality' => 'auto'
                        ]
                    ]
                );
                $data['logo'] = $result['secure_url'];
            } catch (\Exception $e) {
                return response()->json(['message' => 'Failed to upload logo: ' . $e->getMessage()], 500);
            }
        } else if ($request->has('logo') && $request->logo === null) {
            // If logo is explicitly set to null, delete the existing logo
            if ($company && $company->logo) {
                $this->deleteCloudinaryImage($company->logo);
            }
            $data['logo'] = null;
        }

        if ($company) {
            $company->update($data);
        } else {
            $company = Company::create($data);
        }

        return response()->json($company);
    }

    /**
     * Delete image from Cloudinary
     */
    private function deleteCloudinaryImage($url)
    {
        try {
            if (!$url) return;

            // Extract public_id from URL
            preg_match('/company\/[^.]+/', $url, $matches);
            if (isset($matches[0])) {
                $this->cloudinary->destroy($matches[0]);
            }
        } catch (\Exception $e) {
            // Log error but don't stop execution
            \Log::error('Failed to delete Cloudinary image: ' . $e->getMessage());
        }
    }
}
