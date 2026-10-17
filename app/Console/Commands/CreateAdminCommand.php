<?php

namespace App\Console\Commands;

use App\Domain\Employees\EmployeeNumber;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password as promptPassword;
use function Laravel\Prompts\text;

class CreateAdminCommand extends Command
{
    protected $signature = 'primeit:admin
        {--name= : Full name}
        {--email= : Login email}
        {--password= : Password (prefer the prompt or the ADMIN_PASSWORD env var — flags end up in shell history)}';

    protected $description = 'Create a super administrator, or reset the password of an existing one.';

    public function handle(EmployeeNumber $numbers): int
    {
        $email = $this->option('email')
            ?: (config('primeit.admin.email') && ! $this->input->isInteractive() ? config('primeit.admin.email') : null)
            ?: text('Admin email', required: true);

        $existing = User::query()->where('email', $email)->first();

        $name = $this->option('name')
            ?: $existing?->name
            ?: (! $this->input->isInteractive() ? (string) config('primeit.admin.name') : text('Full name', default: (string) config('primeit.admin.name'), required: true));

        $secret = $this->option('password')
            ?: config('primeit.admin.password')
            ?: (! $this->input->isInteractive() ? null : promptPassword('Password', required: true));

        if ($secret === null || $secret === '') {
            $this->error('No password supplied. Pass --password, set ADMIN_PASSWORD, or run interactively.');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['email' => $email, 'password' => $secret],
            ['email' => ['required', 'email'], 'password' => ['required', Password::defaults()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name'              => $name,
                'password'          => Hash::make((string) $secret),
                'email_verified_at' => $existing?->email_verified_at ?? now(),
                'status'            => 'active',
            ],
        );

        $user->syncRoles(['super_admin']);

        if (! Employee::query()->where('user_id', $user->id)->exists()) {
            // $parts = preg_split('/\s+/', trim($name), 2) ?: [$name];
            $parts = preg_split('/\s+/', trim($name), 2) ?: [$name];

            Employee::query()->create([
                'user_id'         => $user->id,
                'employee_number' => $numbers->next(),
                'first_name'      => $parts[0],
                'last_name'       => $parts[1] ?? '-',
                'job_title'       => 'Administrator',
                'employment_type' => 'full_time',
                'hire_date'       => now()->toDateString(),
                'status'          => 'active',
            ]);
        }

        $this->info(($existing ? 'Updated' : 'Created') . " administrator {$email}.");

        return self::SUCCESS;
    }
}
