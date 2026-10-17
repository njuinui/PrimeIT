<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\TwoFactorService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CurrentPasswordRequest;
use App\Http\Requests\Auth\TwoFactorCodeRequest;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'enabled'                   => $user->hasTwoFactorEnabled(),
            'confirmed_at'              => $user->two_factor_confirmed_at?->toISOString(),
            'recovery_codes_remaining'  => $user->hasTwoFactorEnabled()
                ? $this->twoFactor->recoveryCodeCount($user)
                : 0,
        ]);
    }

    // public function enable(Request $request): JsonResponse
    // {
    //     $user = $request->user();

    //     if ($user->hasTwoFactorEnabled()) {
    //         return $this->error('Two-factor authentication is already enabled.', 409);
    //     }

    //     $secret = $this->twoFactor->generateSecret();

    //     $user->forceFill(['two_factor_secret' => encrypt($secret)])->save();

    //     return $this->success([
    //         'secret'       => $secret,
    //         'otpauth_uri'  => $this->twoFactor->otpauthUri($user, $secret),
    //     ], 'Scan the QR code with your authenticator app, then confirm with a code.');
    // }

    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return $this->error(
                'Two-factor authentication is already enabled.',
                409
            );
        }

        $secret = $this->twoFactor->generateSecret();

        $user->forceFill([
            'two_factor_secret' => $secret,
        ])->save();

        return $this->success([
            'secret' => $secret,
            'otpauth_uri' => $this->twoFactor->otpauthUri($user, $secret),
        ], 'Scan the QR code with your authenticator app, then confirm with a code.');
    }

    // public function confirm(TwoFactorCodeRequest $request): JsonResponse
    // {
    //     $user = $request->user();

    //     if ($user->two_factor_secret === null) {
    //         return $this->error('Two-factor setup has not been started.', 400);
    //     }

    //     if ($user->hasTwoFactorEnabled()) {
    //         return $this->error('Two-factor authentication is already enabled.', 409);
    //     }

    //     if (! $this->twoFactor->verifyCode($user->two_factor_secret, (string) $request->validated('code'))) {
    //         LoginLog::record($user, 'two_factor_confirm_failed', false, $request);

    //         throw ValidationException::withMessages([
    //             'code' => ['The provided code is invalid. Check your authenticator and try again.'],
    //         ]);
    //     }

    //     $codes = $this->twoFactor->generateRecoveryCodes();

    //     $user->forceFill([
    //         'two_factor_confirmed_at'      => now(),
    //         'two_factor_recovery_codes'    => encrypt((string) json_encode($codes)),
    //     ])->save();

    //     LoginLog::record($user, 'two_factor_enabled', true, $request);

    //     // The only time recovery codes are ever returned in plain text.
    //     return $this->success(['codes' => $codes], 'Two-factor enabled. Store these recovery codes somewhere safe.');
    // }

    public function confirm(TwoFactorCodeRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->two_factor_secret === null) {
            return $this->error(
                'Two-factor setup has not been started.',
                400
            );
        }

        if ($user->hasTwoFactorEnabled()) {
            return $this->error(
                'Two-factor authentication is already enabled.',
                409
            );
        }

        if (! $this->twoFactor->verifyCode(
            $user->two_factor_secret,
            (string) $request->validated('code')
        )) {
            LoginLog::record(
                $user,
                'two_factor_confirm_failed',
                false,
                $request
            );

            throw ValidationException::withMessages([
                'code' => [
                    'The provided code is invalid. Check your authenticator and try again.',
                ],
            ]);
        }

        $codes = $this->twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => json_encode($codes),
        ])->save();

        LoginLog::record(
            $user,
            'two_factor_enabled',
            true,
            $request
        );

        return $this->success(
            ['codes' => $codes],
            'Two-factor enabled. Store these recovery codes somewhere safe.'
        );
    }

    public function disable(CurrentPasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check((string) $request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->forceFill([
            'two_factor_secret'           => null,
            'two_factor_recovery_codes'    => null,
            'two_factor_confirmed_at'      => null,
        ])->save();

        LoginLog::record($user, 'two_factor_disabled', true, $request);

        return $this->success(message: 'Two-factor authentication has been disabled.');
    }

    public function regenerate(CurrentPasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return $this->error('Two-factor authentication is not enabled.', 400);
        }

        if (! Hash::check((string) $request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $codes = $this->twoFactor->generateRecoveryCodes();

        $this->twoFactor->storeRecoveryCodes($user, $codes);

        LoginLog::record($user, 'recovery_codes_regenerated', true, $request);

        return $this->success(['codes' => $codes], 'New recovery codes generated. All previous codes are now invalid.');
    }
}
