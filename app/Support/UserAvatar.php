<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Storage;

final class UserAvatar
{
    /**
     * Public URL of a user's profile photo (stored on their employee record).
     *
     * Returns null when the photo is missing OR the `employee` relation was not
     * eager-loaded — callers must load `user.employee` to get photos.
     */
    public static function url(?User $user): ?string
    {
        if ($user === null || ! $user->relationLoaded('employee')) {
            return null;
        }

        $avatar = $user->employee?->avatar;

        return $avatar ? Storage::disk('public')->url($avatar) : null;
    }
}
