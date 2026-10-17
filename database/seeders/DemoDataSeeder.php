<?php

namespace Database\Seeders;

use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\Budget;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectMilestone;
use App\Models\ProjectRisk;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\TaskComment;
use App\Models\TaskTimeEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\Meeting;
use App\Models\MeetingMinutes;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    /**
     * Demo departments.
     */
    private const DEPARTMENTS = [
        [
            'name' => 'Engineering',
            'code' => 'ENG',
            'description' => 'Platform, web and mobile engineering.',
        ],
        [
            'name' => 'Product & Design',
            'code' => 'PRD',
            'description' => 'Product management, UX and visual design.',
        ],
        [
            'name' => 'Finance & Operations',
            'code' => 'FIN',
            'description' => 'Accounting, budgets and operations.',
        ],
        [
            'name' => 'People & Culture',
            'code' => 'HR',
            'description' => 'Talent acquisition and internal culture.',
        ],
    ];


    private const EXPENSE_CATEGORIES = [
        ['Travel',               'Flights, hotels and ground transport.'],
        ['Meals & Entertainment', 'Client dinners and team meals.'],
        ['Software & Subscriptions', 'Licences, SaaS and cloud services.'],
        ['Office Supplies',      'Stationery and small equipment.'],
        ['Training & Certification', 'Courses, exams and conferences.'],
        ['Equipment',            'Laptops, phones and peripherals.'],
    ];

    private function seedFinance(array $projects, array $employees): void
    {
        $admin  = User::query()->first();
        $fin    = $employees['laura.bih@primeit.com'] ?? null;
        $people = collect($employees)->values()->take(8);

        $categories = collect(self::EXPENSE_CATEGORIES)->mapWithKeys(
            fn(array $category) => [$category[0] => ExpenseCategory::updateOrCreate(
                ['name' => $category[0]],
                ['description' => $category[1], 'is_active' => true, 'created_by' => $admin?->id],
            )],
        );

        $projectIds = collect($projects)
            ->pluck('id')
            ->filter()
            ->values();

        if ($projectIds->isEmpty()) {
            throw new \RuntimeException(
                'Cannot seed expenses because no projects were created.'
            );
        }

        // [category, title, amount, status, months-ago, submitter-index]
        $template = [
            ['Travel',                 'Client workshop — Douala trip',    185000, 'submitted', 0, 3],
            ['Software & Subscriptions', 'Figma team licence — Q1',         96000,  'submitted', 1, 2],
            ['Meals & Entertainment',   'Dinner with Client Portal stakeholders', 42000, 'submitted', 0, 4],
            ['Equipment',               'MacBook Pro 14" — replacement',    1450000, 'approved',  1, 1],
            ['Training & Certification', 'AWS Solutions Architect exam',    210000,  'approved',  2, 5],
            ['Software & Subscriptions', 'Datadog APM — annual plan',       480000,  'approved',  3, 6],
            ['Office Supplies',         'Standing desks batch #2',          620000,  'paid',     2, 0],
            ['Travel',                  'Conference — Nairobi summit',      540000,  'paid',     3, 4],
            ['Equipment',               'Studio lighting — design team',    310000,  'paid',     4, 9],
            ['Meals & Entertainment',   'Sprint retrospective lunch',       38000,  'rejected', 0, 6],
            ['Office Supplies',         'Print paper & toner',              55000,  'rejected', 1, 7],
            ['Training & Certification', 'Remote facilitation course',      120000,  'draft',    0, 2],
            ['Software & Subscriptions', 'Notion Plus — 5 seats',           60000,  'draft',    0, 8],
        ];

        foreach ($template as $index => [$categoryName, $title, $amount, $status, $monthsAgo, $personIndex]) {
            $submitter = $people[$personIndex] ?? $people[0];
            $user      = User::query()->find($submitter->user_id);

            $expense = Expense::create([
                'expense_number'      => sprintf('EXP-%04d', $index + 1),
                'user_id'             => $user->id,
                'employee_id'         => $submitter->id,
                'expense_category_id' => $categories[$categoryName]->id,
                'project_id'          => $projectIds[$index % $projectIds->count()],
                'department_id'       => $submitter->department_id,
                'title'               => $title,
                'amount'              => $amount,
                'currency'            => 'XAF',
                'expense_date'        => now()->subMonths($monthsAgo)->subDays(rand(0, 20))->toDateString(),
                'status'              => $status,
            ]);

            $expense->recordHistory($user, 'created', null, 'draft');

            if ($status !== 'draft') {
                $expense->recordHistory($user, 'submitted', 'draft', 'submitted');
            }

            if (in_array($status, ['approved', 'paid'], true)) {
                $expense->forceFill([
                    'decided_by' => $fin?->user_id ?? $admin?->id,
                    'decided_at' => now()->subMonths($monthsAgo)->addDays(3),
                    'decision_note' => 'Receipt verified.',
                ])->save();

                $expense->recordHistory($fin ? User::query()->find($fin->user_id) : $admin, 'approved', 'submitted', 'approved', 'Receipt verified.');
            }

            if ($status === 'rejected') {
                $expense->forceFill([
                    'decided_by'   => $fin?->user_id ?? $admin?->id,
                    'decided_at'   => now()->subMonths($monthsAgo)->addDays(2),
                    'decision_note' => 'Missing itemized receipt — please resubmit.',
                ])->save();

                $expense->recordHistory($fin ? User::query()->find($fin->user_id) : $admin, 'rejected', 'submitted', 'rejected', 'Missing itemized receipt — please resubmit.');
            }

            if ($status === 'paid') {
                $payment = Payment::create([
                    'expense_id' => $expense->id,
                    'user_id'    => $fin?->user_id ?? $admin?->id,
                    'amount'     => $amount,
                    'currency'   => 'XAF',
                    'method'     => 'bank_transfer',
                    'reference'  => 'TRF-' . sprintf('%06d', rand(100000, 999999)),
                    'paid_at'    => now()->subMonths($monthsAgo)->addDays(10)->toDateString(),
                ]);

                $expense->forceFill(['status' => 'paid', 'paid_at' => $payment->paid_at])->save();

                $expense->recordHistory($fin ? User::query()->find($fin->user_id) : $admin, 'paid', 'approved', 'paid');
            }
        }

        // Budgets: two project budgets + one department budget.
        $first = $projectIds->get(0);
        $second = $projectIds->get(1);

        Budget::updateOrCreate(
            ['project_id' => $first, 'department_id' => null],
            ['amount' => 2500000, 'currency' => 'XAF', 'description' => 'PrimeIT Mobile App — delivery budget', 'created_by' => $admin?->id],
        );

        Budget::updateOrCreate(
            ['project_id' => $second, 'department_id' => null],
            ['amount' => 1200000, 'currency' => 'XAF', 'description' => 'Client Portal Redesign — design budget', 'created_by' => $admin?->id],
        );

        Budget::updateOrCreate(
            ['project_id' => null, 'department_id' => $employees['john.doe@primeit.com']->department_id],
            [
                'amount' => 3500000,
                'currency' => 'XAF',
                'description' => 'Engineering — annual operating budget',
                'start_date' => now()->startOfYear()->toDateString(),
                'end_date' => now()->endOfYear()->toDateString(),
                'created_by' => $admin?->id
            ],
        );

        // Phase 12: submitted expenses ride the active workflow.
        Expense::query()->where('status', 'submitted')->get()->each(function (Expense $expense): void {
            app(\App\Domain\Approvals\ApprovalEngine::class)
                ->startFor($expense, User::query()->find($expense->user_id));
        });
    }

    private function seedApprovalWorkflows(): void
    {
        $admin = User::query()->first();

        // The active default: single-step finance review.
        $standard = ApprovalWorkflow::updateOrCreate(
            ['name' => 'Expense approval — standard'],
            [
                'entity_type' => 'expense',
                'description' => 'Single finance review for every submitted expense.',
                'is_active'   => true,
                'created_by'  => $admin?->id,
            ],
        );

        $standard->steps()->delete();

        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $standard->id,
            'step_number'          => 1,
            'name'                 => 'Finance review',
            'approver_source'      => 'role',
            'approver_role'        => 'finance_manager',
            'policy'               => 'any_one',
            'escalation_days'      => 3,
        ]);

        // An inactive two-step template admins can activate to demo multi-step.
        $board = ApprovalWorkflow::updateOrCreate(
            ['name' => 'Expense approval — finance then line manager'],
            [
                'entity_type' => 'expense',
                'description' => 'Finance review followed by the requester\'s line manager.',
                'is_active'   => false,
                'created_by'  => $admin?->id,
            ],
        );

        $board->steps()->delete();

        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $board->id,
            'step_number'          => 1,
            'name'                 => 'Finance review',
            'approver_source'      => 'role',
            'approver_role'        => 'finance_manager',
            'policy'               => 'any_one',
            'escalation_days'      => 2,
        ]);

        ApprovalWorkflowStep::create([
            'approval_workflow_id' => $board->id,
            'step_number'          => 2,
            'name'                 => 'Line manager sign-off',
            'approver_source'      => 'requester_manager',
            'policy'               => 'any_one',
            'escalation_days'      => 2,
        ]);
    }

    /**
     * Demo teams.
     *
     * Structure:
     *
     * DEPARTMENT CODE => [
     *     [team name, team code],
     * ]
     */
    private const TEAMS = [
        [
            'ENG' => [
                ['Platform', 'PLT'],
                ['Web & Mobile', 'WEB'],
            ],
        ],
        [
            'PRD' => [
                ['Design Systems', 'DSN'],
            ],
        ],
        [
            'FIN' => [
                ['Accounting', 'ACC'],
            ],
        ],
        [
            'HR' => [
                ['Talent', 'TAL'],
            ],
        ],
    ];

    /**
     * Demo employees.
     *
     * Structure:
     *
     * [
     *     first name,
     *     last name,
     *     department code,
     *     team code,
     *     job title,
     *     role,
     *     manager email
     * ]
     */
    private const PEOPLE = [
        [
            'John',
            'Doe',
            'ENG',
            'PLT',
            'Engineering Manager',
            'manager',
            'admin@primeit.com',
        ],
        [
            'Sarah',
            'Ngono',
            'ENG',
            'PLT',
            'Senior Backend Engineer',
            'employee',
            'john.doe@primeit.com',
        ],
        [
            'Michael',
            'Tabi',
            'ENG',
            'WEB',
            'Frontend Engineer',
            'employee',
            'john.doe@primeit.com',
        ],
        [
            'Grace',
            'Ateba',
            'ENG',
            'WEB',
            'Frontend Engineer',
            'employee',
            'john.doe@primeit.com',
        ],
        [
            'David',
            'Manga',
            'ENG',
            'PLT',
            'DevOps Engineer',
            'employee',
            'john.doe@primeit.com',
        ],
        [
            'Daniel',
            'Fon',
            'ENG',
            'PLT',
            'Backend Engineer',
            'employee',
            'john.doe@primeit.com',
        ],
        [
            'Aline',
            'Ngo',
            'ENG',
            'WEB',
            'QA Engineer',
            'employee',
            'john.doe@primeit.com',
        ],
        [
            'Brice',
            'Talla',
            'ENG',
            'WEB',
            'Junior Frontend Engineer',
            'employee',
            'michael.tabi@primeit.com',
        ],
        [
            'Guy',
            'Sole',
            'ENG',
            'PLT',
            'Data Engineer',
            'employee',
            'john.doe@primeit.com',
        ],
        [
            'Emma',
            'Chewa',
            'PRD',
            'DSN',
            'Design Lead',
            'manager',
            'admin@primeit.com',
        ],
        [
            'Peter',
            'Nkeng',
            'PRD',
            'DSN',
            'Product Designer',
            'employee',
            'emma.chewa@primeit.com',
        ],
        [
            'Clara',
            'Ewane',
            'PRD',
            'DSN',
            'UX Researcher',
            'employee',
            'emma.chewa@primeit.com',
        ],
        [
            'Marta',
            'Biya',
            'PRD',
            'DSN',
            'Illustrator',
            'employee',
            'emma.chewa@primeit.com',
        ],
        [
            'Laura',
            'Bih',
            'FIN',
            'ACC',
            'Finance Manager',
            'finance_manager',
            'admin@primeit.com',
        ],
        [
            'Eric',
            'Fondo',
            'FIN',
            'ACC',
            'Accountant',
            'employee',
            'laura.bih@primeit.com',
        ],
        [
            'Felix',
            'Akoa',
            'FIN',
            'ACC',
            'Financial Analyst',
            'employee',
            'laura.bih@primeit.com',
        ],
        [
            'Rachel',
            'Ngu',
            'HR',
            'TAL',
            'HR Manager',
            'hr',
            'admin@primeit.com',
        ],
        [
            'Diane',
            'Abena',
            'HR',
            'TAL',
            'Talent Acquisition Specialist',
            'employee',
            'rachel.ngu@primeit.com',
        ],
        [
            'Samuel',
            'Etta',
            'HR',
            'TAL',
            'HR Officer',
            'employee',
            'rachel.ngu@primeit.com',
        ],
    ];

    /**
     * Demo projects.
     *
     * Structure:
     *
     * [
     *     project number,
     *     name,
     *     status,
     *     priority,
     *     budget,
     *     progress,
     *     owner email,
     *     department code
     * ]
     */
    private const PROJECTS = [
        [
            'PRJ-0001',
            'PrimeIT Mobile App',
            'in_progress',
            'high',
            4500000,
            45,
            'john.doe@primeit.com',
            'ENG',
        ],
        [
            'PRJ-0002',
            'Client Portal Redesign',
            'in_progress',
            'medium',
            2800000,
            62,
            'emma.chewa@primeit.com',
            'PRD',
        ],
        [
            'PRJ-0003',
            'ERP Integration',
            'planning',
            'high',
            6200000,
            8,
            'laura.bih@primeit.com',
            'FIN',
        ],
        [
            'PRJ-0004',
            'Website Revamp',
            'completed',
            'low',
            1500000,
            100,
            'emma.chewa@primeit.com',
            'PRD',
        ],
        [
            'PRJ-0005',
            'Data Warehouse Migration',
            'on_hold',
            'medium',
            3800000,
            30,
            'john.doe@primeit.com',
            'ENG',
        ],
        [
            'PRJ-0006',
            'Security Audit & Hardening',
            'in_progress',
            'urgent',
            1200000,
            20,
            'john.doe@primeit.com',
            'ENG',
        ],
    ];

    /**
     * Demo milestone titles.
     */
    private const MILESTONE_TITLES = [
        'Discovery & Requirements',
        'Design Approval',
        'Beta Release',
        'Production Launch',
    ];

    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        /*
     * Never seed demo data in production.
     */
        if (app()->environment('production')) {
            return;
        }

        /*
     * Seed core workspace data inside a transaction.
     *
     * Departments
     *     ↓
     * Teams
     *     ↓
     * Users + Employees
     *     ↓
     * Leadership
     *     ↓
     * Projects
     *     ↓
     * Tasks
     *     ↓
     * Task Extras
     *     ↓
     * Project Extras
     *
     * We return employees and projects from the transaction
     * because Finance and Notifications are seeded afterward.
     */
        [$employees, $projects] = DB::transaction(function (): array {
            $departments = $this->seedDepartments();

            $teams = $this->seedTeams($departments);

            $employees = $this->seedEmployees(
                $departments,
                $teams
            );

            $this->seedLeadership($employees);

            $projects = $this->seedProjects(
                $employees,
                $departments
            );

            $this->seedTasks($projects);

            $this->seedTaskExtras($projects);

            $this->seedProjectExtras(
                $projects,
                $employees
            );

            return [
                $employees,
                $projects,
            ];
        });


        $this->seedApprovalWorkflows();

        /*
     * Finance + notifications run outside the transaction so a
     * failure there doesn't roll back the entire workspace.
     */
        $this->seedFinance($projects, $employees);

        $this->seedNotifications($projects);
        $this->seedMeetings($employees);
    }

        private function seedMeetings(array $employees): void
    {
        $admin = User::query()->first();
        $john  = $employees['john.doe@primeit.com'];
        $laura = $employees['laura.bih@primeit.com'];

        $mk = function (array $attributes) use ($admin): Meeting {
            return Meeting::create(array_merge([
                'status'     => 'scheduled',
                'created_by' => $admin?->id,
            ], $attributes));
        };

        $attach = function (Meeting $meeting, array $crew, ?int $organizerId) use ($admin): void {
            $map = [];

            if ($organizerId !== null) {
                $map[$organizerId] = [
                    'attendance' => 'required', 'response' => 'going',
                    'responded_at' => now()->subDay(), 'added_by' => $admin?->id,
                ];
            }

            foreach ($crew as $index => [$employee, $attendance, $response]) {
                if (! isset($map[$employee->id])) {
                    $map[$employee->id] = [
                        'attendance' => $attendance,
                        'response'   => $response,
                        'responded_at' => $response !== 'waiting' ? now()->subHours(5 + $index) : null,
                        'added_by'   => $admin?->id,
                    ];
                }
            }

            $meeting->participants()->attach($map);
        };

        $eng = [
            [$employees['sarah.ngono@primeit.com'], 'required', 'going'],
        ];

        // 1) Starting in ~10 minutes — the 15-minute reminder target.
        $standup = $mk([
            'title'       => 'Engineering standup — weekly sync',
            'description' => "Agenda:\n- Sprint progress vs plan\n- Blockers & dependencies\n- Demo slots",
            'location'    => 'Room A · 2nd floor',
            'starts_at'   => now()->addMinutes(10),
            'ends_at'     => now()->addMinutes(40),
            'organized_by' => $john->id,
        ]);

        $attach($standup, [
            [$employees['sarah.ngono@primeit.com'], 'required', 'going'],
            [$employees['michael.tabi@primeit.com'], 'required', 'going'],
            [$employees['david.manga@primeit.com'], 'optional', 'maybe'],
            [$employees['grace.ateba@primeit.com'], 'required', 'waiting'],
        ], $john->id);

        // 2) Tomorrow-ish — the 24-hour reminder target.
        $review = $mk([
            'title'       => 'Quarterly budget review',
            'description' => "Agenda:\n- Q spend vs budgets\n- Pending approvals\n- Forecast adjustments",
            'video_link'  => 'https://meet.example.com/primeit/qbr',
            'starts_at'   => now()->addHours(20),
            'ends_at'     => now()->addHours(21),
            'organized_by' => $laura->id,
        ]);

        $attach($review, [
            [$employees['john.doe@primeit.com'], 'required', 'going'],
            [$employees['eric.fondo@primeit.com'], 'required', 'waiting'],
        ], $laura->id);

        // 3) Next week — plain upcoming.
        $planning = $mk([
            'title'       => 'Mobile App v2 planning workshop',
            'description' => "Agenda:\n- Scope for v2\n- Architecture decisions\n- Timeline & staffing",
            'location'    => 'Room B · 2nd floor',
            'starts_at'   => now()->addDays(6)->setTime(10, 0),
            'ends_at'     => now()->addDays(6)->setTime(12, 30),
            'organized_by' => $john->id,
        ]);

        $attach($planning, [
            [$employees['sarah.ngono@primeit.com'], 'required', 'waiting'],
            [$employees['peter.nkeng@primeit.com'], 'optional', 'waiting'],
            [$employees['emma.chewa@primeit.com'], 'required', 'going'],
        ], $john->id);

        // 4) Past + completed with published minutes and two action items (one already a task).
        $retro = $mk([
            'title'       => 'Sprint 14 retrospective',
            'description' => 'What went well, what to improve, actions.',
            'location'    => 'Room A · 2nd floor',
            'starts_at'   => now()->subDays(4)->setTime(15, 0),
            'ends_at'     => now()->subDays(4)->setTime(16, 0),
            'status'      => 'completed',
            'organized_by' => $john->id,
        ]);

        $attach($retro, [
            [$employees['sarah.ngono@primeit.com'], 'required', 'going'],
            [$employees['michael.tabi@primeit.com'], 'required', 'going'],
            [$employees['aline.ngo@primeit.com'], 'required', 'declined'],
        ], $john->id);

        MeetingMinutes::create([
            'meeting_id'   => $retro->id,
            'content'      => "Highlights:\n- CI flakiness cost ~3h across the sprint\n- Design handoff went smoothly\n\nDecisions:\n- Adopt trunk-based development\n- Weekly demo slot on Fridays",
            'action_items' => [
                [
                    'title'      => 'Stabilize the CI pipeline',
                    'assignee_id' => $employees['david.manga@primeit.com']->uuid,
                    'due_date'   => now()->addDays(10)->toDateString(),
                    'task_id'    => null,
                ],
                [
                    'title'      => 'Draft the weekly demo format',
                    'assignee_id' => $employees['emma.chewa@primeit.com']->uuid,
                    'due_date'   => now()->addDays(5)->toDateString(),
                    'task_id'    => null,
                ],
            ],
            'recorded_by'  => $john->user_id,
            'published_at' => now()->subDays(3),
        ]);

        // 5) Cancelled last week.
        $cancelled = $mk([
            'title'       => 'Vendor negotiation prep',
            'starts_at'   => now()->subDays(7)->setTime(11, 0),
            'ends_at'     => now()->subDays(7)->setTime(12, 0),
            'status'      => 'cancelled',
            'organized_by' => $laura->id,
        ]);

        $attach($cancelled, [
            [$employees['eric.fondo@primeit.com'], 'required', 'declined'],
        ], $laura->id);
    }

    /**
     * Seed departments.
     *
     * @return array<string, Department>
     */
    private function seedDepartments(): array
    {
        return collect(self::DEPARTMENTS)
            ->mapWithKeys(
                function (array $department): array {
                    $model = Department::updateOrCreate(
                        [
                            'code' => $department['code'],
                        ],
                        [
                            'name' => $department['name'],
                            'description' => $department['description'],
                            'is_active' => true,
                        ]
                    );

                    return [
                        $department['code'] => $model,
                    ];
                }
            )
            ->all();
    }

    /**
     * Seed teams.
     *
     * @param array<string, Department> $departments
     *
     * @return array<string, Team>
     */
    private function seedTeams(array $departments): array
    {
        $teams = [];

        foreach (self::TEAMS as $group) {
            foreach ($group as $departmentCode => $definitions) {
                if (! isset($departments[$departmentCode])) {
                    throw new \RuntimeException(
                        "Demo department [{$departmentCode}] was not found."
                    );
                }

                foreach ($definitions as [$name, $code]) {
                    $teams[$code] = Team::updateOrCreate(
                        [
                            'code' => $code,
                        ],
                        [
                            'department_id' => $departments[$departmentCode]->id,
                            'name' => $name,
                            'is_active' => true,
                        ]
                    );
                }
            }
        }

        return $teams;
    }

    /**
     * Seed users and employees.
     *
     * @param array<string, Department> $departments
     * @param array<string, Team> $teams
     *
     * @return array<string, Employee>
     */
    private function seedEmployees(
        array $departments,
        array $teams
    ): array {
        $employees = [];

        /*
         * EMP-0001 belongs to the initial admin/super-admin.
         */
        $number = 2;

        foreach (
            self::PEOPLE as [
                $first,
                $last,
                $departmentCode,
                $teamCode,
                $jobTitle,
                $role,
                $managerEmail,
            ]
        ) {
            $email = strtolower(
                "{$first}.{$last}@primeit.com"
            );

            if (! isset($departments[$departmentCode])) {
                throw new \RuntimeException(
                    "Demo department [{$departmentCode}] was not found for {$email}."
                );
            }

            if (! isset($teams[$teamCode])) {
                throw new \RuntimeException(
                    "Demo team [{$teamCode}] was not found for {$email}."
                );
            }

            /*
             * Create/update user account.
             */
            $user = User::updateOrCreate(
                [
                    'email' => $email,
                ],
                [
                    'name' => "{$first} {$last}",
                    'password' => Hash::make('PrimeIT@2026'),
                    'email_verified_at' => now(),
                    'status' => 'active',
                ]
            );

            /*
             * Give the user exactly the intended demo role.
             */
            $user->syncRoles([$role]);

            /*
             * Resolve the manager.
             *
             * The initial admin may already have an employee profile
             * created by AdminUserSeeder. Other managers are created
             * before their direct reports because PEOPLE is ordered
             * accordingly.
             */
            $managerId = null;

            if ($managerEmail !== null) {
                $managerId = Employee::query()
                    ->whereHas(
                        'user',
                        fn($query) => $query->where(
                            'email',
                            strtolower($managerEmail)
                        )
                    )
                    ->value('id');
            }

            /*
             * Create/update employee profile.
             */
            $employees[$email] = Employee::updateOrCreate(
                [
                    'user_id' => $user->id,
                ],
                [
                    'employee_number' => sprintf(
                        'EMP-%04d',
                        $number++
                    ),
                    'first_name' => $first,
                    'last_name' => $last,
                    'department_id' => $departments[$departmentCode]->id,
                    'team_id' => $teams[$teamCode]->id,
                    'manager_id' => $managerId,
                    'job_title' => $jobTitle,
                    'employment_type' => 'full_time',
                    'hire_date' => now()
                        ->subDays(rand(120, 900))
                        ->toDateString(),
                    'status' => 'active',
                ]
            );
        }

        /*
         * Add employment/status variation so frontend filters
         * can be tested properly.
         */
        $variations = [
            'guy.sole@primeit.com' => [
                'employment_type' => 'contract',
            ],

            'marta.biya@primeit.com' => [
                'employment_type' => 'part_time',
            ],

            'brice.talla@primeit.com' => [
                'employment_type' => 'intern',
            ],

            'samuel.etta@primeit.com' => [
                'status' => 'inactive',
            ],
        ];

        foreach ($variations as $email => $attributes) {
            if (isset($employees[$email])) {
                $employees[$email]
                    ->forceFill($attributes)
                    ->save();
            }
        }

        return $employees;
    }

    /**
     * Seed department managers and team leaders.
     *
     * @param array<string, Employee> $employees
     */
    private function seedLeadership(array $employees): void
    {
        $leadership = [
            'departments' => [
                'ENG' => 'john.doe@primeit.com',
                'PRD' => 'emma.chewa@primeit.com',
                'FIN' => 'laura.bih@primeit.com',
                'HR' => 'rachel.ngu@primeit.com',
            ],

            'teams' => [
                'PLT' => 'john.doe@primeit.com',
                'WEB' => 'michael.tabi@primeit.com',
                'DSN' => 'emma.chewa@primeit.com',
                'ACC' => 'laura.bih@primeit.com',
                'TAL' => 'rachel.ngu@primeit.com',
            ],
        ];

        foreach ($leadership['departments'] as $code => $email) {
            if (! isset($employees[$email])) {
                throw new \RuntimeException(
                    "Department leader [{$email}] was not found."
                );
            }

            Department::where('code', $code)->update([
                'manager_id' => $employees[$email]->id,
            ]);
        }

        foreach ($leadership['teams'] as $code => $email) {
            if (! isset($employees[$email])) {
                throw new \RuntimeException(
                    "Team leader [{$email}] was not found."
                );
            }

            Team::where('code', $code)->update([
                'leader_id' => $employees[$email]->id,
            ]);
        }
    }

    /**
     * Seed projects.
     *
     * @param array<string, Employee> $employees
     * @param array<string, Department> $departments
     *
     * @return array<int, Project>
     */
    private function seedProjects(
        array $employees,
        array $departments
    ): array {
        /*
         * The initial admin is created by AdminUserSeeder.
         */
        $admin = User::query()
            ->where('email', 'admin@primeit.com')
            ->first();

        if ($admin === null) {
            throw new \RuntimeException(
                'The demo administrator [admin@primeit.com] was not found. Run AdminUserSeeder first.'
            );
        }

        return collect(self::PROJECTS)
            ->map(
                function (array $projectData) use (
                    $employees,
                    $departments,
                    $admin
                ): Project {
                    [
                        $projectNumber,
                        $name,
                        $status,
                        $priority,
                        $budget,
                        $progress,
                        $ownerEmail,
                        $departmentCode,
                    ] = $projectData;

                    if (! isset($employees[$ownerEmail])) {
                        throw new \RuntimeException(
                            "Demo project owner [{$ownerEmail}] was not found."
                        );
                    }

                    if (! isset($departments[$departmentCode])) {
                        throw new \RuntimeException(
                            "Demo department [{$departmentCode}] was not found."
                        );
                    }

                    $owner = $employees[$ownerEmail];

                    return Project::updateOrCreate(
                        [
                            'project_number' => $projectNumber,
                        ],
                        [
                            'name' => $name,
                            'slug' => Str::slug($name),
                            'description' => "Demo project for {$name}.",
                            'status' => $status,
                            'priority' => $priority,
                            'owner_id' => $owner->id,
                            'project_manager_id' => $owner->id,
                            'department_id' => $departments[$departmentCode]->id,
                            'start_date' => now()
                                ->subDays(rand(30, 120))
                                ->toDateString(),
                            'due_date' => now()
                                ->addDays(rand(20, 140))
                                ->toDateString(),
                            'completed_at' => $status === 'completed'
                                ? now()->subDays(7)
                                : null,
                            'budget' => $budget,
                            'currency' => 'XAF',
                            'progress' => $progress,
                            'created_by' => $admin->id,
                        ]
                    );
                }
            )
            ->values()
            ->all();
    }

    /**
     * Seed tasks for every demo project.
     *
     * @param array<int, Project> $projects
     */
    private function seedTasks(array $projects): void
    {
        foreach ($projects as $project) {
            /*
             * Only create tasks if this project currently has
             * no tasks. This prevents duplicate demo tasks when
             * the seeder is executed repeatedly.
             */
            if ($project->tasks()->exists()) {
                continue;
            }

            Task::factory()
                ->count(rand(5, 9))
                ->for($project)
                ->create();
        }
    }

    /**
     * Seed:
     *
     * - project members
     * - project milestones
     * - project risks
     * - project activities
     *
     * @param array<int, Project> $projects
     * @param array<string, Employee> $employees
     */
    private function seedProjectExtras(
        array $projects,
        array $employees
    ): void {
        /*
         * Resolve the demo administrator explicitly rather than
         * relying on User::first().
         */
        $admin = User::query()
            ->where('email', 'admin@primeit.com')
            ->first();

        if ($admin === null) {
            throw new \RuntimeException(
                'The demo administrator [admin@primeit.com] was not found.'
            );
        }

        $people = collect($employees)
            ->values();

        if ($people->isEmpty()) {
            return;
        }

        foreach ($projects as $project) {
            /*
             * Resolve project manager.
             */
            $manager = $people->first(
                fn(Employee $employee): bool =>
                $employee->id === $project->project_manager_id
            );

            /*
             * Select 3-4 additional employees.
             *
             * Exclude:
             * - project manager
             *
             * The project manager is added separately below.
             */
            $crew = $people
                ->filter(
                    fn(Employee $employee): bool =>
                    $employee->id !== $project->project_manager_id
                )
                ->shuffle()
                ->take(rand(3, 4));

            /*
             * Always include the project manager.
             */
            $members = collect();

            if ($manager !== null) {
                $members->push($manager);
            }

            $members = $members
                ->merge($crew)
                ->unique('id')
                ->values();

            /*
             * ---------------------------------------------------------
             * PROJECT MEMBERS
             * ---------------------------------------------------------
             */
            foreach ($members as $index => $employee) {
                $memberRole = match (true) {
                    $employee->id === $project->project_manager_id
                    => 'project_manager',

                    $index === 1
                    => 'lead',

                    default
                    => 'member',
                };

                /*
                 * The Project model uses the ProjectMember pivot.
                 */
                $project->members()->syncWithoutDetaching([
                    $employee->id => [
                        'role' => $memberRole,
                        'added_by' => $admin->id,
                    ],
                ]);

                /*
                 * Use EVENT, not TYPE.
                 *
                 * project_activities contains:
                 *
                 *     event
                 *
                 * It does NOT contain:
                 *
                 *     type
                 */
                ProjectActivity::firstOrCreate(
                    [
                        'project_id' => $project->id,
                        'user_id' => $admin->id,
                        'event' => 'member_added',
                        'description' =>
                        "Added {$employee->first_name} {$employee->last_name} to the project.",
                        'subject_type' => $employee->getMorphClass(),
                        'subject_id' => $employee->getKey(),
                    ]
                );
            }

            /*
             * ---------------------------------------------------------
             * PROJECT MILESTONES
             * ---------------------------------------------------------
             */
            foreach (self::MILESTONE_TITLES as $index => $title) {
                $sortOrder = $index + 1;

                /*
                 * Keep milestone dates deterministic relative to the
                 * project start rather than generating random dates
                 * every time the seeder runs.
                 */
                $baseDate = $project->start_date
                    ? $project->start_date->copy()
                    : now();

                $dueDate = $baseDate
                    ->addDays(($sortOrder * 25) + 5)
                    ->toDateString();

                /*
                 * Completed projects have completed milestones.
                 *
                 * For active projects, mark the first milestone
                 * complete when progress is at least 30%.
                 */
                $isCompleted =
                    $project->status === 'completed'
                    || (
                        $sortOrder === 1
                        && $project->progress >= 30
                    );

                ProjectMilestone::updateOrCreate(
                    [
                        'project_id' => $project->id,
                        'sort_order' => $sortOrder,
                    ],
                    [
                        'title' => $title,
                        'description' =>
                        "Demo milestone: {$title} for {$project->name}.",
                        'due_date' => $dueDate,
                        'completed_at' => $isCompleted
                            ? $baseDate->copy()->addDays(
                                max(1, ($sortOrder * 20) - 5)
                            )
                            : null,
                        'created_by' => $admin->id,
                    ]
                );
            }

            /*
             * ---------------------------------------------------------
             * PROJECT RISKS
             * ---------------------------------------------------------
             *
             * Only the first three projects receive demo risks.
             */
            if (
                in_array(
                    $project->project_number,
                    [
                        'PRJ-0001',
                        'PRJ-0002',
                        'PRJ-0003',
                    ],
                    true
                )
            ) {
                $riskOwner = $manager ?? $people->first();

                if ($riskOwner !== null) {
                    ProjectRisk::firstOrCreate(
                        [
                            'project_id' => $project->id,
                            'title' => 'Resource availability risk',
                        ],
                        [
                            'description' =>
                            'Key project resources may become unavailable during a critical delivery period.',
                            'impact' => 'high',
                            'likelihood' => 'medium',
                            'status' => 'open',
                            'mitigation' =>
                            'Maintain backup resources and review team allocation weekly.',
                            'owner_id' => $riskOwner->id,
                            'review_date' => now()
                                ->addDays(14)
                                ->toDateString(),
                            'created_by' => $admin->id,
                        ]
                    );

                    ProjectRisk::firstOrCreate(
                        [
                            'project_id' => $project->id,
                            'title' => 'Schedule delay risk',
                        ],
                        [
                            'description' =>
                            'Unplanned technical or business dependencies could affect the project timeline.',
                            'impact' => 'medium',
                            'likelihood' => 'medium',
                            'status' => 'open',
                            'mitigation' =>
                            'Track dependencies closely and review the delivery schedule during project meetings.',
                            'owner_id' => $riskOwner->id,
                            'review_date' => now()
                                ->addDays(21)
                                ->toDateString(),
                            'created_by' => $admin->id,
                        ]
                    );
                }
            }

            /*
             * ---------------------------------------------------------
             * PROJECT CREATED ACTIVITY
             * ---------------------------------------------------------
             */
            ProjectActivity::firstOrCreate(
                [
                    'project_id' => $project->id,
                    'user_id' => $admin->id,
                    'event' => 'project_created',
                    'description' =>
                    "Created demo project {$project->project_number} - {$project->name}.",
                    'subject_type' => $project->getMorphClass(),
                    'subject_id' => $project->getKey(),
                ]
            );

            /*
             * ---------------------------------------------------------
             * COMPLETED MILESTONE ACTIVITY
             * ---------------------------------------------------------
             */
            if (
                $project->status === 'completed'
                || $project->progress >= 30
            ) {
                $milestone = ProjectMilestone::query()
                    ->where('project_id', $project->id)
                    ->whereNotNull('completed_at')
                    ->orderBy('sort_order')
                    ->first();

                if ($milestone !== null) {
                    ProjectActivity::firstOrCreate(
                        [
                            'project_id' => $project->id,
                            'user_id' => $admin->id,
                            'event' => 'milestone_completed',
                            'description' =>
                            "Completed milestone: {$milestone->title}.",
                            'subject_type' => $milestone->getMorphClass(),
                            'subject_id' => $milestone->getKey(),
                        ]
                    );
                }
            }
        }
    }


    private function seedTaskExtras(array $projects): void
    {
        $admin = User::query()->first();

        foreach (array_values($projects) as $project) {
            $tasks = $project->tasks()->orderBy('id')->take(5)->get();

            foreach ($tasks as $task) {
                // A three-step checklist whose completion tracks the seeded progress.
                $doneItems = (int) floor($task->progress / 100 * 3);

                foreach (['Gather requirements', 'Implementation', 'Review & sign-off'] as $c => $label) {
                    TaskChecklistItem::create([
                        'task_id'      => $task->id,
                        'content'      => $label,
                        'is_completed' => $c < $doneItems,
                        'completed_at' => $c < $doneItems ? now()->subDays($doneItems - $c) : null,
                        'sort_order'   => $c + 1,
                        'created_by'   => $admin?->id,
                    ]);
                }

                // Assignees auto-watch their tasks.
                foreach ($task->assignees()->get() as $assignee) {
                    if ($assignee->user_id !== null) {
                        DB::table('task_watchers')->insertOrIgnore([
                            'task_id'    => $task->id,
                            'user_id'    => $assignee->user_id,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                TaskComment::create([
                    'task_id' => $task->id,
                    'user_id' => $admin?->id,
                    'body'    => 'Kicked off — please keep the checklist updated as you go.',
                ]);

                if ($task->status === 'completed') {
                    foreach ([60, 90] as $m => $minutes) {
                        TaskTimeEntry::create([
                            'task_id'   => $task->id,
                            'user_id'   => $admin?->id,
                            'minutes'   => $minutes,
                            'note'      => $m === 0 ? 'Implementation pass' : 'Review & fixes',
                            'worked_at' => now()->subDays($m + 1)->toDateString(),
                        ]);
                    }

                    $task->forceFill(['actual_minutes' => 150])->save();
                }
            }

            // A dependency chain inside each project.
            for ($i = 1; $i < count($tasks); $i++) {
                DB::table('task_dependencies')->insertOrIgnore([
                    'task_id'            => $tasks[$i]->id,
                    'depends_on_task_id' => $tasks[$i - 1]->id,
                    'created_by'         => $admin?->id,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            }
        }
    }

    private function seedNotifications(array $projects): void
    {
        $recipients = [
            'sarah.ngono@primeit.com'  => 'task_assigned',
            'michael.tabi@primeit.com' => 'task_status_changed',
            'grace.ateba@primeit.com'  => 'task_commented',
            'peter.nkeng@primeit.com'  => 'task_mentioned',
        ];

        $tasks = collect($projects)
            ->flatMap(fn($project) => $project->tasks()->orderBy('id')->take(3)->get())
            ->values();

        if ($tasks->isEmpty()) {
            return;
        }

        $now = now();

        foreach ($recipients as $email => $category) {
            $user = User::where('email', $email)->first();

            if ($user === null) {
                continue;
            }

            foreach (range(1, 3) as $i) {
                $task = $tasks[($i - 1) % $tasks->count()];

                $body = match ($category) {
                    'task_assigned'       => sprintf('John Doe assigned you "%s"', $task->title),
                    'task_status_changed' => sprintf('John Doe moved "%s" from To Do to In Progress', $task->title),
                    'task_commented'      => sprintf('John Doe commented on "%s": "Kickoff notes are in the description."', $task->title),
                    default               => sprintf('John Doe mentioned you on "%s": "Can you take the review pass?"', $task->title),
                };

                DB::table('notifications')->insert([
                    'id'              => (string) Str::uuid(),
                    'type'            => 'App\\Notifications\\Tasks\\' . match ($category) {
                        'task_assigned'       => 'TaskAssignedNotification',
                        'task_status_changed' => 'TaskStatusChangedNotification',
                        'task_commented'      => 'TaskCommentedNotification',
                        default               => 'TaskMentionedNotification',
                    },
                    'notifiable_type' => User::class,
                    'notifiable_id'   => $user->id,
                    'data'            => json_encode([
                        'category'     => $category,
                        'title'        => match ($category) {
                            'task_assigned'       => 'New task assignment',
                            'task_status_changed' => 'Task status updated',
                            'task_commented'      => 'New comment on a task you follow',
                            default               => 'You were mentioned',
                        },
                        'body'         => $body,
                        'task_id'      => $task->uuid,
                        'task_number'  => $task->task_number,
                        'project_name' => $task->project()->value('name'),
                        'due_date'     => $task->due_date?->toDateString(),
                    ]),
                    'read_at'    => $i === 3 ? $now->copy()->subHours(9) : null,
                    'created_at' => $now->copy()->subHours($i * 3),
                    'updated_at' => $now->copy()->subHours($i * 3),
                ]);
            }
        }
    }
}
