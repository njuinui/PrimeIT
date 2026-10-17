<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\LoginLogResource;
use App\Models\LoginLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoginHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = LoginLog::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(min((int) $request->query('per_page', 15), 50));

        return $this->paginated($logs, LoginLogResource::class);
    }
}