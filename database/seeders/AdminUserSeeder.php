<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /** Local-development convenience only — never used in production. */
    private const DEV_PASSWORD = 'PrimeIT@2026';

    public function run(): void
    {
        $email    = (string) config('primeit.admin.email');
        $name     = (string) config('primeit.admin.name');
        $password = config('primeit.admin.password');

        if ($password === null || $password === '') {
            if (app()->isProduction()) {
                $this->command?->error(
                    'ADMIN_PASSWORD is not set — refusing to create an administrator with a default password in production. '
                    . 'Set ADMIN_EMAIL / ADMIN_PASSWORD, or run: php artisan primeit:admin'
                );

                return;
            }

            $password = self::DEV_PASSWORD;
        }

        // firstOrCreate: re-running the seeder must never reset an existing account's password.
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name'              => $name,
                'password'          => Hash::make((string) $password),
                'email_verified_at' => now(),
                'status'            => 'active',
            ],
        );

        $user->syncRoles(['super_admin']);

        Employee::firstOrCreate(
            ['user_id' => $user->id],
            [
                'employee_number' => 'EMP-0001',
                'first_name'      => 'PrimeIT',
                'last_name'       => 'Admin',
                'job_title'       => 'Chief Operating Officer',
                'employment_type' => 'full_time',
                'hire_date'       => '2023-01-09',
                'status'          => 'active',
            ],
        );
    }
}
