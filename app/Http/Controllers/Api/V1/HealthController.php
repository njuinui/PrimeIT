<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $database = rescue(fn () => DB::connection()->getPdo() !== null, false);

        return $this->success([
            'status'    => $database ? 'ok' : 'degraded',
            'app'       => config('app.name'),
            'database'  => $database ? 'connected' : 'unavailable',
            'php'       => PHP_VERSION,
            'laravel'   => app()->version(),
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}