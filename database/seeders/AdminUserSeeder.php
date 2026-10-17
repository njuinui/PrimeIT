<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'admin@primeit.com'],
            [
                'name'              => 'PrimeIT Admin',
                'password'          => Hash::make('PrimeIT@2026'),
                'email_verified_at' => now(),
                'status'            => 'active',
            ]
        );
        $user->syncRoles(['super_admin']);

        Employee::updateOrCreate(
            ['user_id' => $user->id],
            [
                'employee_number' => 'EMP-0001',
                'first_name'      => 'PrimeIT',
                'last_name'       => 'Admin',
                'job_title'       => 'Chief Operating Officer',
                'employment_type' => 'full_time',
                'hire_date'       => '2023-01-09',
                'status'          => 'active',
            ]
        );
    }
}