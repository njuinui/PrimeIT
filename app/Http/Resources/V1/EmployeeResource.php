<?php

namespace App\Http\Resources\V1;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Employee
 */
class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Employee Identity
            |--------------------------------------------------------------------------
            */
            'id' => $this->uuid,
            'employee_number' => $this->employee_number,

            /*
            |--------------------------------------------------------------------------
            | Personal Information
            |--------------------------------------------------------------------------
            */
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,

            'full_name' => trim(
                $this->first_name . ' ' . $this->last_name
            ),
            'avatar_url' => $this->avatar ? Storage::disk('public')->url($this->avatar) : null,

            /*
            |--------------------------------------------------------------------------
            | Employment Information
            |--------------------------------------------------------------------------
            */
            'job_title' => $this->job_title,
            'employment_type' => $this->employment_type,
            'hire_date' => $this->hire_date?->toDateString(),
            'status' => $this->status,

            /*
            |--------------------------------------------------------------------------
            | Contact Information
            |--------------------------------------------------------------------------
            */
            'phone' => $this->phone,
            'personal_email' => $this->personal_email,

            /*
            |--------------------------------------------------------------------------
            | Department
            |--------------------------------------------------------------------------
            |
            | Included only when the relationship has been eager loaded.
            |
            */
            'department' => $this->whenLoaded(
                'department',
                fn () => $this->department !== null
                    ? [
                        'id' => $this->department->uuid,
                        'name' => $this->department->name,
                    ]
                    : null
            ),

            /*
            |--------------------------------------------------------------------------
            | Team
            |--------------------------------------------------------------------------
            */
            'team' => $this->whenLoaded(
                'team',
                fn () => $this->team !== null
                    ? [
                        'id' => $this->team->uuid,
                        'name' => $this->team->name,
                    ]
                    : null
            ),

            /*
            |--------------------------------------------------------------------------
            | Manager
            |--------------------------------------------------------------------------
            */
            'manager' => $this->whenLoaded(
                'manager',
                fn () => $this->manager !== null
                    ? [
                        'id' => $this->manager->uuid,
                        'full_name' => trim(
                            $this->manager->first_name . ' ' . $this->manager->last_name
                        ),
                        'job_title' => $this->manager->job_title,
                        'avatar_url' => $this->manager->avatar ? Storage::disk('public')->url($this->manager->avatar) : null,
                    ]
                    : null
            ),

            /*
            |--------------------------------------------------------------------------
            | Associated User Account
            |--------------------------------------------------------------------------
            */
            'user' => $this->whenLoaded(
                'user',
                fn () => $this->user !== null
                    ? [
                        'email' => $this->user->email,
                        'status' => $this->user->status,
                        'email_verified' => $this->user->email_verified_at !== null,
                        'last_login_at' => $this->user->last_login_at?->toISOString(),
                    ]
                    : null
            ),

            /*
            |--------------------------------------------------------------------------
            | Direct Reports Count
            |--------------------------------------------------------------------------
            |
            | This requires the controller/query to use:
            |
            |     ->withCount('directReports')
            |
            | Laravel then exposes:
            |
            |     direct_reports_count
            |
            | The correct JsonResource helper is whenCounted().
            |
            */
            'direct_reports_count' => $this->whenCounted('directReports'),

            /*
            |--------------------------------------------------------------------------
            | Direct Reports
            |--------------------------------------------------------------------------
            |
            | This relationship is only included when it has been eager loaded
            | using:
            |
            |     ->with('directReports')
            |
            */
            'direct_reports' => $this->whenLoaded(
                'directReports',
                fn () => $this->directReports
                    ->map(
                        fn (Employee $report) => [
                            'id' => $report->uuid,
                            'full_name' => trim(
                                $report->first_name . ' ' . $report->last_name
                            ),
                            'job_title' => $report->job_title,
                            'avatar_url' => $report->avatar ? Storage::disk('public')->url($report->avatar) : null,
                        ]
                    )
                    ->values()
                    ->all()
            ),

            /*
            |--------------------------------------------------------------------------
            | Authorization / Capability Flags
            |--------------------------------------------------------------------------
            |
            | These are resolved through EmployeePolicy.
            |
            */
            'can_update' => $request->user()?->can(
                'update',
                $this->resource
            ) ?? false,

            'can_delete' => $request->user()?->can(
                'delete',
                $this->resource
            ) ?? false,

            /*
            |--------------------------------------------------------------------------
            | Timestamp
            |--------------------------------------------------------------------------
            */
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}