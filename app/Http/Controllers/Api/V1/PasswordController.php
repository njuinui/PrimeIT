<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->input('email'))->first();

        Password::sendResetLink($request->validated());

        LoginLog::record($user, 'password_reset_requested', true, $request);

        // Identical response whether or not the account exists (no enumeration).
        return $this->success(message: 'If an account exists for this email address, a password reset link has been sent.');
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password'       => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();
                Auth::forgetGuards();

                event(new PasswordReset($user));

                LoginLog::record($user, 'password_reset', true);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->error(__($status), 422);
        }

        return $this->success(message: 'Your password has been reset. You can now sign in with the new password.');
    }

    public function change(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check((string) $request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->forceFill(['password' => (string) $request->input('password')])->save();

        // Invalidate every session except the one making this request.
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();
        Auth::forgetGuards();

        LoginLog::record($user, 'password_changed', true, $request);

        return $this->success(message: 'Password updated. All other sessions have been signed out.');
    }
}