<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\User
 */
class UserSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->uuid,
            'name'            => $this->name,
            'email'           => $this->email,
            'status'          => $this->status,
            'email_verified'  => $this->email_verified_at !== null,
            'last_login_at'   => $this->last_login_at?->toISOString(),
            'created_at'      => $this->created_at?->toISOString(),

            'employee' => $this->when(
                $this->relationLoaded('employee') && $this->employee !== null,
                fn () => [
                    'id'              => $this->employee->uuid,
                    'employee_number' => $this->employee->employee_number,
                    'job_title'       => $this->employee->job_title,
                    'avatar_url'      => $this->employee->avatar ? Storage::disk('public')->url($this->employee->avatar) : null,
                    'department'      => $this->employee->relationLoaded('department') && $this->employee->department
                        ? ['id' => $this->employee->department->uuid, 'name' => $this->employee->department->name]
                        : null,
                ],
            ),

            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn ($role) => [
                'id'        => $role->uuid,
                'name'      => $role->name,
                'is_system' => (bool) $role->is_system,
            ])->values()->all()),
        ];
    }
}