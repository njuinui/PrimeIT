<?php

namespace App\Domain\Auth;

use App\Models\User;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

final class TwoFactorService
{
    public function __construct(
        private readonly Google2FA $engine,
    ) {}

    /**
     * Generate a new TOTP secret.
     */
    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey(32);
    }

    /**
     * Generate the otpauth URI used by authenticator applications.
     */
    public function otpauthUri(User $user, string $secret): string
    {
        return $this->engine->getQRCodeUrl(
            (string) config('app.name'),
            $user->email,
            $secret,
        );
    }

    /**
     * Verify a TOTP code against the user's decrypted secret.
     *
     * The User model uses Laravel's encrypted cast for
     * two_factor_secret, so Eloquent automatically decrypts
     * the value when it is read.
     */
    public function verifyCode(?string $secret, string $code): bool
    {
        if ($secret === null || $secret === '') {
            return false;
        }

        /**
         * Authenticator applications normally produce a
         * six-digit numeric code.
         *
         * Remove spaces, dashes, or other formatting characters.
         */
        $normalizedCode = preg_replace('/\D+/', '', $code) ?? '';

        if (! preg_match('/^\d{6}$/', $normalizedCode)) {
            return false;
        }

        try {
            return $this->engine->verifyKey(
                $secret,
                $normalizedCode,
                1,
            );
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Generate one-time recovery codes.
     */
    public function generateRecoveryCodes(int $count = 10): array
    {
        return collect(range(1, $count))
            ->map(
                fn (): string =>
                    Str::upper(Str::random(5))
                    . '-'
                    . Str::upper(Str::random(5))
            )
            ->all();
    }

    /**
     * Read currently available recovery codes.
     *
     * The User model's encrypted cast automatically decrypts
     * two_factor_recovery_codes.
     */
    public function readRecoveryCodes(User $user): array
    {
        $stored = $user->two_factor_recovery_codes;

        if ($stored === null || $stored === '') {
            return [];
        }

        try {
            $codes = json_decode($stored, true);

            return is_array($codes)
                ? array_values($codes)
                : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Replace the user's recovery codes.
     *
     * The User model automatically encrypts the value because
     * two_factor_recovery_codes uses Laravel's encrypted cast.
     */
    public function storeRecoveryCodes(
        User $user,
        array $codes,
    ): void {
        $user->forceFill([
            'two_factor_recovery_codes' => json_encode(
                array_values($codes),
                JSON_THROW_ON_ERROR,
            ),
        ])->save();
    }

    /**
     * Count unused recovery codes.
     */
    public function recoveryCodeCount(User $user): int
    {
        return count(
            $this->readRecoveryCodes($user)
        );
    }

    /**
     * Consume a recovery code exactly once.
     */
    public function consumeRecoveryCode(
        User $user,
        string $code,
    ): bool {
        $codes = $this->readRecoveryCodes($user);

        $normalizedCode = Str::upper(
            trim($code)
        );

        $index = array_search(
            $normalizedCode,
            $codes,
            true,
        );

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);

        $this->storeRecoveryCodes(
            $user,
            array_values($codes),
        );

        return true;
    }
}