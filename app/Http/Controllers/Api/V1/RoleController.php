<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rbac\StoreRoleRequest;
use App\Http\Requests\Rbac\SyncRolePermissionsRequest;
use App\Http\Requests\Rbac\UpdateRoleRequest;
use App\Http\Resources\V1\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('roles.view');

        $roles = Role::query()
            ->with('permissions')
            ->withCount('users')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        return $this->success(RoleResource::collection($roles)->resolve());
    }

    public function show(Request $request, Role $role): JsonResponse
    {
        $this->authorize('roles.view');

        $role->loadMissing('permissions')->loadCount('users');

        return $this->success(new RoleResource($role));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $this->authorize('roles.manage');

        $role = Role::create([
            'name'        => $request->validated('name'),
            'guard_name'  => 'web',
            'description' => $request->validated('description'),
            'is_system'   => false,
        ]);

        $role->syncPermissions($request->validated('permissions'));

        return $this->success(new RoleResource($role->loadMissing('permissions')), 'Role created.', 201);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $this->authorize('roles.manage');

        $role->update([
            'name'        => $request->validated('name'),
            'description' => $request->validated('description'),
        ]);

        return $this->success(new RoleResource($role->fresh()->loadMissing('permissions')), 'Role updated.');
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->authorize('roles.manage');

        if ($role->is_system) {
            return $this->error('System roles cannot be deleted.', 403);
        }

        if ($role->users()->count() > 0) {
            return $this->error('This role is still assigned to users. Reassign their roles first.', 409);
        }

        $role->delete();

        return $this->success(message: 'Role deleted.');
    }

    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role): JsonResponse
    {
        $this->authorize('roles.manage');

        if ($role->name === 'super_admin') {
            return $this->error('The super admin role bypasses all permission checks; its permission set cannot be modified.', 403);
        }

        $role->syncPermissions($request->validated('permissions'));

        return $this->success(new RoleResource($role->fresh()->loadMissing('permissions')), 'Permissions updated.');
    }
}