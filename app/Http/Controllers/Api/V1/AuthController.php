<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\IssueLoginToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\V1\SessionResource;
use App\Http\Resources\V1\UserResource;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthController extends Controller
{
    public function __construct(private readonly IssueLoginToken $issueLoginToken) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $email = Str::lower(trim((string) $request->input('email')));

        $user = User::where('email', $email)->first();

        if ($user === null || ! Hash::check((string) $request->input('password'), $user->password)) {
            LoginLog::record($user, 'login_failed', false, $request);

            // Deliberately generic — never reveal which half was wrong.
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $user->isActive()) {
            LoginLog::record($user, 'login_blocked_inactive', false, $request);

            return $this->error('Your account is inactive. Please contact an administrator.', 403);
        }

        if ($user->hasTwoFactorEnabled()) {
            return $this->startTwoFactorChallenge($user, $request);
        }

        return $this->success(($this->issueLoginToken)($user, $request), 'Signed in successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->tokens()->delete();
        $request->setUserResolver(fn () => null);
        Auth::forgetGuards();

        LoginLog::record($user, 'logout', true, $request);

        return $this->success(message: 'Signed out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(new UserResource(
            $request->user()->loadMissing('employee.department', 'employee.team')
        ));
    }

    public function updateProfilePhoto(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $employee = $request->user()->employee()->first();

        if ($employee === null) {
            return $this->error('A linked employee profile is required to set a profile photo.', 422);
        }

        $photoPath = $validated['photo']->store('employees/avatars', 'public');

        if ($photoPath === false) {
            return $this->error('Unable to store the profile photo. Please try again.', 500);
        }

        $previousPhoto = $employee->avatar;

        try {
            $employee->forceFill(['avatar' => $photoPath])->save();
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($photoPath);
            throw $exception;
        }

        if ($previousPhoto !== null) {
            Storage::disk('public')->delete($previousPhoto);
        }

        $user = $request->user()->unsetRelation('employee')->load('employee.department', 'employee.team');

        return $this->success(new UserResource($user), 'Profile photo updated.');
    }

    public function sessions(Request $request): JsonResponse
    {
        $tokens = $request->user()
            ->tokens()
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get();

        return $this->success(SessionResource::collection($tokens)->resolve());
    }

    public function revokeSession(Request $request, int $id): JsonResponse
    {
        $deleted = $request->user()->tokens()->where('id', $id)->delete();

        if ($deleted === 0) {
            return $this->error('Session not found.', 404);
        }

        Auth::forgetGuards();
        LoginLog::record($request->user(), 'token_revoked', true, $request);

        return $this->success(message: 'Session revoked.');
    }

    public function revokeOtherSessions(Request $request): JsonResponse
    {
        $currentId = $request->user()->currentAccessToken()->id;

        $revoked = $request->user()->tokens()->where('id', '!=', $currentId)->delete();
        Auth::forgetGuards();

        LoginLog::record($request->user(), 'tokens_revoked_others', true, $request);

        return $this->success(['revoked' => $revoked], 'All other sessions have been signed out.');
    }

    // ── Internals ─────────────────────────────────────────────

    private function startTwoFactorChallenge(User $user, LoginRequest $request): JsonResponse
    {
        $challengeToken = Str::uuid()->toString();

        Cache::put(
            "2fa_challenge:{$challengeToken}",
            ['user_id' => $user->id, 'attempts' => 0],
            now()->addMinutes(10),
        );

        LoginLog::record($user, 'two_factor_challenge_started', true, $request);

        return $this->success([
            'two_factor_required' => true,
            'challenge_token'     => $challengeToken,
        ], 'Two-factor authentication is required for this account.');
    }
}