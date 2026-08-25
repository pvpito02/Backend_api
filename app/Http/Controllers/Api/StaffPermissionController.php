<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\StaffPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffPermissionController extends Controller
{
    /** Catalogue des cases à cocher (création / édition compte RH). */
    public function catalog(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403, 'Accès réservé au Super Admin.');

        $items = [];
        foreach (StaffPermissions::CATALOG as $key => $label) {
            $items[] = ['key' => $key, 'label' => $label];
        }

        return response()->json([
            'data' => $items,
            'defaults' => StaffPermissions::defaults(),
        ]);
    }
}
