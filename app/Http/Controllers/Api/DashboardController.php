<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardStatsService $stats) {}

    public function summary(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdminStaff(), 403, 'Accès non autorisé.');

        return response()->json($this->stats->summary($request->input('date')));
    }

    public function alerts(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdminStaff(), 403, 'Accès non autorisé.');

        return response()->json($this->stats->alerts());
    }
}
