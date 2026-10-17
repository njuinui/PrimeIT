<?php

namespace App\Domain\Auth;

use App\Http\Resources\V1\UserResource;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Single place where a successful sign-in materializes:
 * token creation, last_login_at and the audit entry.
 */
final class IssueLoginToken
{
    public function __invoke(User $user, Request $request, string $event = 'login'): array
    {
        $name = (string) $request->input('device_name', '');

        $token = $user->createToken($name !== '' ? Str::limit($name, 100) : 'Unknown device');

        $user->forceFill(['last_login_at' => now()])->save();

        LoginLog::record($user, $event, true, $request);

        return [
            'token' => $token->plainTextToken,
            'user'  => new UserResource( $user->loadMissing('employee.department', 'employee.team', 'roles.permissions', 'permissions')),
        ];
    }
}