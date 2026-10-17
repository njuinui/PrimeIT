<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Models\LoginLog;
use App\Models\User;
use App\Support\SignedFrontendUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function verify(VerifyEmailRequest $request): JsonResponse
    {
        $id        = (int) $request->validated('id');
        $hash      = (string) $request->validated('hash');
        $expires   = (int) $request->validated('expires');
        $signature = (string) $request->validated('signature');

        $signatureValid = SignedFrontendUrl::matches('/verify-email', [
            'id'      => (string) $id,
            'hash'    => $hash,
            'expires' => (string) $expires,
        ], $signature);

        if (! $signatureValid) {
            return $this->error('This verification link is invalid.', 422);
        }

        if ($expires < now()->getTimestamp()) {
            return $this->error('This verification link has expired. Request a new one from your account settings.', 422);
        }

        $user = User::find($id);

        if ($user === null || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return $this->error('This verification link is invalid.', 422);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            LoginLog::record($user, 'email_verified', true, $request);
        }

        return $this->success(message: 'Your email address has been verified.');
    }

    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return $this->success(message: 'Your email address is already verified.');
        }

        $user->sendEmailVerificationNotification();

        return $this->success(message: 'A fresh verification link has been sent to your email address.');
    }
}