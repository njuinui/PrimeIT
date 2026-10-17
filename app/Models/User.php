<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Notifications\Auth\ResetPasswordNotification;
use App\Notifications\Auth\VerifyEmailNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasPublicUuid;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    /**
     * Attributes that can be mass assigned.
     */
    protected $fillable = [
        'uuid',
        'name',
        'email',
        'phone',
        'password',
        'status',
        'two_factor_enabled',
        'last_login_at',
    ];

    /**
     * Attributes hidden from arrays/JSON.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',

            'last_login_at' => 'datetime',

            'two_factor_enabled' => 'boolean',

            'two_factor_confirmed_at' => 'datetime',

            /*
             * These values are encrypted automatically by Laravel.
             *
             * Do NOT manually call encrypt(), decrypt(),
             * Crypt::encryptString(), or Crypt::decryptString()
             * when reading/writing these attributes.
             */
            'two_factor_secret' => 'encrypted',

            'two_factor_recovery_codes' => 'encrypted',

            /*
             * Laravel automatically hashes passwords when
             * assigning a new password.
             */
            'password' => 'hashed',
        ];
    }

    /**
     * Employee profile associated with the user.
     */
    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * Reminders owned by the user.
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    /**
     * Authentication/login history.
     */
    public function loginLogs(): HasMany
    {
        return $this->hasMany(LoginLog::class);
    }

    /**
     * Determine whether two-factor authentication is enabled.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    /**
     * Determine whether the account can authenticate.
     */
    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->deleted_at === null;
    }

    /**
     * Send email verification notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(
            new VerifyEmailNotification()
        );
    }

    /**
     * Send password reset notification.
     */
    public function sendPasswordResetNotification(
        $token
    ): void {
        $this->notify(
            new ResetPasswordNotification($token)
        );
    }
}