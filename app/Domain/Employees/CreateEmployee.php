<?php

namespace App\Domain\Employees;

use App\Models\Employee;
use App\Models\User;
use App\Notifications\Employee\EmployeeInvitedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class CreateEmployee
{
    public function __construct(private readonly EmployeeNumber $numbers) {}

    /**
     * @param array<string, mixed> $employeeData validated employee attributes
     */
    public function __invoke(array $employeeData, string $email): Employee
    {
        $avatar = $employeeData['avatar'] ?? null;
        unset($employeeData['avatar']);
        $avatarPath = $avatar?->store('employees/avatars', 'public') ?: null;

        try {
            $employee = DB::transaction(function () use ($employeeData, $email, $avatarPath): Employee {
                $user = User::create([
                    'name'     => trim($employeeData['first_name'].' '.$employeeData['last_name']),
                    'email'    => $email,
                    'phone'    => $employeeData['phone'] ?? null,
                    'password' => Str::random(40), // unusable until the invite is accepted
                    'status'   => 'active',
                ]);

                $user->assignRole('employee'); // HR can change this later via the Users page

                return Employee::create([
                    ...$employeeData,
                    'avatar'          => $avatarPath,
                    'user_id'         => $user->id,
                    'employee_number' => $this->numbers->next(),
                ]);
            });
        } catch (Throwable $exception) {
            if ($avatarPath !== null) {
                Storage::disk('public')->delete($avatarPath);
            }

            throw $exception;
        }

        $employee->user()->getEager()->first()?->notify(new EmployeeInvitedNotification());

        return $employee;
    }
}