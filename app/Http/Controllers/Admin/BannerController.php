<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\Setting;
use Illuminate\Http\Request;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;

class BannerController extends Controller
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

    public function index()
    {
        $banners = Banner::orderBy('sort_order')->get();
        return response()->json($banners);
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp'
            ]);

            \Log::info('Uploading banner image to Cloudinary');
            $uploadResult = $this->cloudinary->upload(
                $request->file('image')->getRealPath(),
                ['folder' => 'banners']
            );
            \Log::info('Image uploaded successfully', ['url' => $uploadResult['secure_url']]);

            $banner = Banner::create([
                'image' => $uploadResult['secure_url'],
                'sort_order' => Banner::count() + 1
            ]);

            return response()->json($banner, 201);
        } catch (\Exception $e) {
            \Log::error('Banner upload failed: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'message' => 'Failed to upload banner',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $banner = Banner::findOrFail($id);
            $this->deleteCloudinaryImage($banner->image);
            $banner->delete();

            return response()->json(['message' => 'Banner deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to delete banner'], 500);
        }
    }

    public function updateOrder(Request $request)
    {
        $request->validate([
            'orders' => 'required|array'
        ]);

        foreach ($request->orders as $order) {
            Banner::where('id', $order['id'])->update(['sort_order' => $order['position']]);
        }

        return response()->json(['message' => 'Order updated successfully']);
    }

    private function deleteCloudinaryImage($url)
    {
        try {
            if (!$url) return;

            preg_match('/banners\/[^.]+/', $url, $matches);
            if (isset($matches[0])) {
                $this->cloudinary->destroy($matches[0]);
            }
        } catch (\Exception $e) {
            \Log::error('Failed to delete Cloudinary image: ' . $e->getMessage());
        }
    }
}
