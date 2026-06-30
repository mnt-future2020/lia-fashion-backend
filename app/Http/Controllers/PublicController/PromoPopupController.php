<?php

namespace App\Http\Controllers\PublicController;

use App\Http\Controllers\Controller;
use App\Models\PromoPopup;

class PromoPopupController extends Controller
{
    // Returns the most recent enabled popup matching the requested page
    public function show()
    {
        $page = request('page');
        $popup = PromoPopup::query()
            ->where('enabled', true)
            ->orderByDesc('id')
            ->get()
            ->first(function ($p) use ($page) {
                $targets = (array) ($p->target_pages ?? []);
                if (empty($targets)) return true; // if no targets, allow everywhere
                if ($page && in_array($page, $targets)) return true;
                return false;
            });

        if (!$popup) {
            return response()->json(['enabled' => false]);
        }

        return response()->json($popup);
    }
}


