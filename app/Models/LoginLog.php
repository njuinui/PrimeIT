<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class LoginLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'email', 'event', 'successful', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Central audit writer used by every auth controller.
     */
    public static function record(?User $user, string $event, bool $successful = true, ?Request $request = null): self
    {
        $request ??= request();

        $userAgent = $request?->userAgent();

        return static::create([
            'user_id'    => $user?->id,
            'email'      => $user?->email ?? (string) ($request?->input('email') ?? 'unknown'),
            'event'      => $event,
            'successful' => $successful,
            'ip_address' => $request?->ip(),
            'user_agent' => $userAgent !== null ? substr($userAgent, 0, 500) : null,
        ]);
    }
}