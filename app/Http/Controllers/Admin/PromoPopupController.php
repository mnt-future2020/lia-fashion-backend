<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromoPopup;
use Illuminate\Http\Request;

class PromoPopupController extends Controller
{
    public function index()
    {
        $perPage = (int) request('per_page', 20);
        if ($perPage === -1 || request('per_page') === 'all') {
            return response()->json(['data' => PromoPopup::orderByDesc('id')->get()]);
        }
        return PromoPopup::orderByDesc('id')->paginate($perPage);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $popup = PromoPopup::create($data);
        return response()->json($popup, 201);
    }

    public function show($id)
    {
        return PromoPopup::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $popup = PromoPopup::findOrFail($id);
        $popup->update($this->validateData($request, $popup->id));
        return response()->json($popup);
    }

    public function destroy($id)
    {
        $popup = PromoPopup::findOrFail($id);
        $popup->delete();
        return response()->json(['success' => true]);
    }

    protected function validateData(Request $request, $id = null): array
    {
        $validated = $request->validate([
            'enabled' => ['sometimes','boolean'],
            'title' => ['nullable','string','max:255'],
            'message' => ['nullable','string'],
            'cta_label' => ['nullable','string','max:255'],
            'cta_url' => ['nullable','string','max:2048'],
            'image' => ['nullable','string','max:2048'],
            'theme' => ['nullable','array'],
            'target_pages' => ['nullable','array'],
            'frequency' => ['nullable','in:always,once,daily'],
        ]);
        return $validated;
    }
}


