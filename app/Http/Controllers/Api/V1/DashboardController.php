<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Dashboard\BuildDashboard;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly BuildDashboard $buildDashboard) {}

    public function stats(Request $request): JsonResponse
    {
        $this->authorize('dashboard.view');

        $days = (int) $request->query('days', 14);

        if (! in_array($days, [7, 14, 30], true)) {
            $days = 14;
        }

        return $this->success(
            ($this->buildDashboard)($request->user(), $days)
        );
    }
}