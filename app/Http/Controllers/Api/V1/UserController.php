<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rbac\SyncUserRolesRequest;
use App\Http\Resources\V1\UserSummaryResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('users.view');

        $search = trim((string) $request->query('search'));
        $role   = (string) $request->query('role');
        $status = (string) $request->query('status');

        $users = User::query()
            ->with(['employee.department', 'roles'])
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->when($role !== '', fn ($query) => $query->whereHas('roles', fn ($q) => $q->where('name', $role)))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderBy('name')
            ->paginate(min((int) $request->query('per_page', 15), 50));

        return $this->paginated($users, UserSummaryResource::class);
    }

    public function updateRoles(SyncUserRolesRequest $request, User $user): JsonResponse
    {
        $this->authorize('users.update');

        // Self-escalation / self-lockout guard.
        if ($user->is($request->user())) {
            return $this->error('You cannot change your own roles. Ask another administrator to do this.', 403);
        }

        $currentRoles = $user->getRoleNames();
        $newRoles     = collect($request->validated('roles'))->unique()->values();

        // Only a super admin may grant the super admin role.
        if ($newRoles->contains('super_admin') && ! $request->user()->hasRole('super_admin')) {
            return $this->error('Only a super admin can grant the super admin role.', 403);
        }

        // Never demote the last super admin.
        if ($currentRoles->contains('super_admin') && ! $newRoles->contains('super_admin')) {
            $remainingSuperAdmins = User::role('super_admin')
                ->where('id', '!=', $user->id)
                ->exists();

            if (! $remainingSuperAdmins) {
                return $this->error('This user is the last super admin — their role cannot be removed.', 403);
            }
        }

        $user->syncRoles($newRoles->all());

        return $this->success(
            new UserSummaryResource($user->fresh(['employee.department', 'roles'])),
            'Roles updated.',
        );
    }
}