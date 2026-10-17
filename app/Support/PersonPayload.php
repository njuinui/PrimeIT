<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

final class PersonPayload
{
    /**
     * Compact public identity of a user. Expects `employee` to be eager-loaded.
     *
     * @return array<string, mixed>|null
     */
    public static function make(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $employee = $user->relationLoaded('employee') ? $user->employee : null;

        return [
            'id'         => $user->uuid,
            'name'       => $user->name,
            'job_title'  => $employee?->job_title,
            'avatar_url' => $employee?->avatar ? Storage::disk('public')->url($employee->avatar) : null,
        ];
    }
}
