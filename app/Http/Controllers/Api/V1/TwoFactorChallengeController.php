<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\IssueLoginToken;
use App\Domain\Auth\TwoFactorService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class TwoFactorChallengeController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly IssueLoginToken $issueLoginToken,
    ) {}

    public function verify(TwoFactorChallengeRequest $request): JsonResponse
    {
        $resolved = $this->resolveChallenge($request);

        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        [$user, $data, $key] = $resolved;

        if (! $this->twoFactor->verifyCode($user->two_factor_secret, (string) $request->input('code'))) {
            return $this->registerFailedAttempt($request, $user, $data, $key, 'two_factor_failed', 'code', 'The provided two-factor code is invalid.');
        }

        Cache::forget($key);

        return $this->success(($this->issueLoginToken)($user, $request), 'Signed in successfully.');
    }

    public function recovery(TwoFactorChallengeRequest $request): JsonResponse
    {
        $resolved = $this->resolveChallenge($request);

        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        [$user, $data, $key] = $resolved;

        if (! $this->twoFactor->consumeRecoveryCode($user, (string) $request->input('recovery_code'))) {
            return $this->registerFailedAttempt($request, $user, $data, $key, 'two_factor_recovery_failed', 'recovery_code', 'The provided recovery code is invalid.');
        }

        Cache::forget($key);

        return $this->success(
            ($this->issueLoginToken)($user, $request, 'login_via_recovery_code'),
            'Signed in successfully.',
        );
    }

    // ── Internals ─────────────────────────────────────────────

    /**
     * @return \Illuminate\Http\JsonResponse|array{0: \App\Models\User, 1: array, 2: string}
     */
    private function resolveChallenge(TwoFactorChallengeRequest $request): JsonResponse|array
    {
        $token = (string) $request->validated('challenge_token');
        $key   = "2fa_challenge:{$token}";
        $data  = Cache::get($key);

        if (! is_array($data)) {
            return $this->error('This two-factor challenge is invalid or has expired. Please sign in again.', 422);
        }

        $user = User::find($data['user_id'] ?? null);

        if ($user === null || ! $user->isActive() || ! $user->hasTwoFactorEnabled()) {
            Cache::forget($key);

            return $this->error('This two-factor challenge is invalid or has expired. Please sign in again.', 422);
        }

        if ((int) ($data['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
            Cache::forget($key);

            LoginLog::record($user, 'two_factor_failed', false, $request);

            return $this->error('Too many incorrect attempts. Please start the sign-in again.', 422);
        }

        return [$user, $data, $key];
    }

    private function registerFailedAttempt(
        TwoFactorChallengeRequest $request,
        User $user,
        array $data,
        string $key,
        string $event,
        string $field,
        string $message,
    ): JsonResponse {
        $data['attempts'] = ((int) ($data['attempts'] ?? 0)) + 1;

        Cache::put($key, $data, now()->addMinutes(10));

        LoginLog::record($user, $event, false, $request);

        throw ValidationException::withMessages([$field => [$message]]);
    }
}