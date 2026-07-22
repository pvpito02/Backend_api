<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /** Liste des rôles actifs (pour formulaires admin). */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdminStaff(), 403, 'Accès non autorisé.');

        $roles = Role::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name', 'display_name', 'description']);

        return response()->json(['data' => $roles]);
    }
}
