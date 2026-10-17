<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->uuid,
            'name'               => $this->name,
            'email'              => $this->email,
            'phone'              => $this->phone,
            'status'             => $this->status,
            'email_verified'     => $this->email_verified_at !== null,
            'two_factor_enabled' => $this->two_factor_confirmed_at !== null,
            'last_login_at'      => $this->last_login_at?->toISOString(),
            'created_at'         => $this->created_at?->toISOString(),

            'employee' => $this->when(
                $this->relationLoaded('employee') && $this->employee !== null,
                fn () => [
                    'id'              => $this->employee->uuid,
                    'employee_number' => $this->employee->employee_number,
                    'first_name'      => $this->employee->first_name,
                    'last_name'       => $this->employee->last_name,
                    'full_name'       => trim($this->employee->first_name.' '.$this->employee->last_name),
                    'avatar_url'      => $this->employee->avatar ? Storage::disk('public')->url($this->employee->avatar) : null,
                    'job_title'       => $this->employee->job_title,
                    'department'      => $this->employee->relationLoaded('department') && $this->employee->department
                        ? ['id' => $this->employee->department->uuid, 'name' => $this->employee->department->name]
                        : null,
                    'team' => $this->employee->relationLoaded('team') && $this->employee->team
                        ? ['id' => $this->employee->team->uuid, 'name' => $this->employee->team->name]
                        : null,
                ],
            ),

            'roles'       => $this->getRoleNames()->all(),
            'permissions' => $this->getAllPermissions()->pluck('name')->all(),
        ];
    }
}