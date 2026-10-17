<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class StatusController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'name'   => config('app.name'),
            'status' => 'ok',
            'api'    => url('/api/v1'),
        ]);
    }
}
