<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Employees\CreateEmployee;
use App\Domain\Employees\UpdateEmployee;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreEmployeeRequest;
use App\Http\Requests\Company\UpdateEmployeeRequest;
use App\Http\Resources\V1\EmployeeResource;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EmployeeController extends Controller
{
    private const RELATIONS = ['department', 'team', 'manager', 'user'];

    public function __construct(
        private readonly CreateEmployee $createEmployee,
        private readonly UpdateEmployee $updateEmployee,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('employees.view');

        $search         = trim((string) $request->query('search'));
        $departmentId   = $this->uuidToId(Department::class, (string) $request->query('department'));
        $teamId         = $this->uuidToId(Team::class, (string) $request->query('team'));
        $managerId      = $this->uuidToId(Employee::class, (string) $request->query('manager'));
        $status         = (string) $request->query('status');
        $employmentType = (string) $request->query('employment_type');

        $employees = Employee::query()
            ->with(self::RELATIONS)
            ->withCount('directReports')
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('employee_number', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($user) => $user
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))))
            ->when($departmentId !== null, fn ($query) => $query->where('department_id', $departmentId))
            ->when($teamId !== null, fn ($query) => $query->where('team_id', $teamId))
            ->when($managerId !== null, fn ($query) => $query->where('manager_id', $managerId))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($employmentType !== '', fn ($query) => $query->where('employment_type', $employmentType))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return $this->paginated($employees, EmployeeResource::class);
    }

    /**
     * Lightweight picker list (id + name + title + department) for
     * manager/leader/assignee selects across the app.
     */
    public function options(Request $request): JsonResponse
    {
        $this->authorize('employees.view');

        $employees = Employee::query()
            ->active()
            ->with('department:id,uuid,name')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'uuid', 'first_name', 'last_name', 'job_title', 'department_id']);

        return $this->success($employees->map(fn (Employee $employee) => [
            'id'         => $employee->uuid,
            'full_name'  => trim($employee->first_name.' '.$employee->last_name),
            'job_title'  => $employee->job_title,
            'department' => $employee->department?->name,
            'avatar_url' => $employee->avatar ? Storage::disk('public')->url($employee->avatar) : null,
        ])->all());
    }

    public function show(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);

        $employee->loadMissing(self::RELATIONS)->loadCount('directReports');

        return $this->success(array_merge(
            (new EmployeeResource($employee))->resolve($request),
            [
                'manager_chain' => $employee->managerChain(5)->map(fn (Employee $manager) => [
                    'id'        => $manager->uuid,
                    'full_name' => trim($manager->first_name.' '.$manager->last_name),
                    'job_title' => $manager->job_title,
                    'avatar_url' => $manager->avatar ? Storage::disk('public')->url($manager->avatar) : null,
                ])->values()->all(),
            ],
        ));
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $this->authorize('employees.create');

        $employee = ($this->createEmployee)(
            collect($request->validated())->except('email')->all(),
            (string) $request->validated('email'),
        );

        $employee->loadMissing(self::RELATIONS)->loadCount('directReports');

        return $this->success(
            new EmployeeResource($employee),
            'Employee created. An invitation email has been sent to set their password.',
            201,
        );
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $this->authorize('update', $employee);

        ($this->updateEmployee)($employee, $request->validated());

        $employee->loadMissing(self::RELATIONS)->loadCount('directReports');

        return $this->success(new EmployeeResource($employee), 'Employee updated.');
    }

    public function destroy(Request $request, Employee $employee): JsonResponse
    {
        if ($employee->user_id === $request->user()->id) {
            return $this->error('You cannot delete your own employee record.', 403);
        }

        $this->authorize('delete', $employee);

        if ($employee->directReports()->exists()) {
            return $this->error('This employee still has direct reports. Reassign them to another manager first.', 409);
        }

        $employee->loadMissing('user');

        $employee->delete();

        if ($employee->user !== null) {
            $employee->user->tokens()->delete();
            Auth::forgetGuards();
            $employee->user->forceFill(['status' => 'inactive'])->save();
        }

        return $this->success(message: 'Employee deleted. Their account has been deactivated.');
    }

    private function uuidToId(string $model, string $uuid): ?int
    {
        if ($uuid === '') {
            return null;
        }

        return $model::query()->where('uuid', $uuid)->value('id');
    }
}