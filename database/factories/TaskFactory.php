<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TaskFactory extends Factory
{
    private const TITLES = [
        'Design login screen flow', 'Implement OAuth2 client', 'Fix flaky CI pipeline',
        'Write API documentation', 'Set up staging environment', 'Migrate images to CDN',
        'Add pagination to task list', 'Refactor notification service', 'Create onboarding email sequence',
        'Integrate payment gateway', 'Update dependency versions', 'Improve search relevance',
        'Add dark mode support', 'Write unit tests for billing', 'Prepare quarterly finance report',
        'Conduct usability testing sessions', 'Optimize database queries', 'Configure backup automation',
        'Draft data retention policy', 'Review access permissions', 'Build Kanban drag interactions',
        'Set up error monitoring', 'Create expense approval workflow', 'Audit SCIM provisioning',
    ];

    public function definition(): array
    {
        $status = fake()->randomElement([
            'backlog', 'todo', 'todo', 'todo', 'in_progress', 'in_progress',
            'review', 'blocked', 'completed', 'completed', 'completed',
        ]);

        $title = fake()->randomElement(self::TITLES);
        $due   = $status === 'completed'
            ? now()->subDays(rand(1, 10))
            : now()->addDays(rand(-4, 21));

        return [
            'task_number'       => 'TSK-'.fake()->unique()->numberBetween(1000, 9999),
            'title'             => $title,
            'slug'              => Str::slug($title).'-'.fake()->unique()->numberBetween(100, 999),
            'description'       => fake()->optional()->paragraph(),
            'status'            => $status,
            'priority'          => fake()->randomElement(['low', 'medium', 'medium', 'high', 'urgent']),
            'created_by'        => fn () => User::first()?->id,
            'start_date'        => now()->subDays(rand(0, 20)),
            'due_date'          => $due->copy()->setTime(rand(9, 17), rand(0, 59)),
            'completed_at'      => $status === 'completed'
                ? now()->subDays(rand(0, 13))->setTime(rand(9, 18), rand(0, 59))
                : null,
            'estimated_minutes' => rand(1, 8) * 60,
            'actual_minutes'    => $status === 'completed' ? rand(1, 9) * 60 : 0,
            'progress'          => match ($status) {
                'completed'   => 100,
                'review'      => rand(85, 95),
                'in_progress' => rand(20, 70),
                'blocked'     => rand(10, 50),
                default       => 0,
            },
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Task $task): void {
            $task->assignees()->sync(
                Employee::inRandomOrder()->take(rand(1, 2))->get()->mapWithKeys(
                    fn (Employee $employee) => [$employee->id => [
                        'assigned_by' => $task->created_by,
                        'assigned_at' => $task->created_at ?? now(),
                    ]]
                )->all()
            );
        });
    }
}