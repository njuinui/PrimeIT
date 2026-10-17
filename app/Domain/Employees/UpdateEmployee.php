<?php

namespace App\Domain\Employees;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class UpdateEmployee
{
    /**
     * Full-replace update. Keeps the linked user's name in sync and
     * tears down (or restores) account access when status flips.
     *
     * @param array<string, mixed> $data validated employee attributes
     */
    public function __invoke(Employee $employee, array $data): Employee
    {
        $avatar = $data['avatar'] ?? null;
        $removeAvatar = (bool) ($data['remove_avatar'] ?? false);
        unset($data['avatar'], $data['remove_avatar']);

        $oldAvatar = $employee->avatar;
        $newAvatarPath = $avatar?->store('employees/avatars', 'public') ?: null;

        if ($newAvatarPath !== null) {
            $data['avatar'] = $newAvatarPath;
        } elseif ($removeAvatar) {
            $data['avatar'] = null;
        }

        try {
            $updated = DB::transaction(function () use ($employee, $data): Employee {
                $wasActive = $employee->status === 'active';

                $employee->update($data);

                if ($employee->user_id !== null) {
                    $user = User::query()->find($employee->user_id);

                    if ($user !== null) {
                        $attributes = [
                            'name' => trim($employee->first_name.' '.$employee->last_name),
                        ];

                        if ($wasActive && $employee->status !== 'active') {
                            $attributes['status'] = 'inactive';
                        } elseif (! $wasActive && $employee->status === 'active') {
                            $attributes['status'] = 'active';
                        }

                        $user->forceFill($attributes)->save();

                        if (($attributes['status'] ?? null) === 'inactive') {
                            $user->tokens()->delete();
                            Auth::forgetGuards();
                        }
                    }
                }

                return $employee;
            });
        } catch (Throwable $exception) {
            if ($newAvatarPath !== null) {
                Storage::disk('public')->delete($newAvatarPath);
            }

            throw $exception;
        }

        if (($newAvatarPath !== null || $removeAvatar) && $oldAvatar !== null) {
            Storage::disk('public')->delete($oldAvatar);
        }

        return $updated;
    }
}