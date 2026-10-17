<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Permissions\Catalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('roles.view');

        $groups = collect(Catalog::groups())
            ->map(fn (array $permissions, string $group) => [
                'group'       => $group,
                'permissions' => collect($permissions)
                    ->map(fn (string $description, string $name) => [
                        'name'        => $name,
                        'description' => $description,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return $this->success($groups);
    }
}