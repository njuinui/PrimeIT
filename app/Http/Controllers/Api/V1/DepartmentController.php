<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreDepartmentRequest;
use App\Http\Requests\Company\UpdateDepartmentRequest;
use App\Http\Resources\V1\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('departments.view');

        $departments = Department::query()
            ->with('manager')
            ->withCount(['employees', 'teams'])
            ->orderBy('name')
            ->get();

        return $this->success(DepartmentResource::collection($departments)->resolve());
    }

    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $this->authorize('departments.manage');

        $department = Department::create([
            'name'        => $request->validated('name'),
            'code'        => strtoupper($request->validated('code')),
            'description' => $request->validated('description'),
            'manager_id'  => $request->validated('manager_id'),
            'is_active'   => (bool) $request->validated('is_active', true),
        ]);

        $department->loadMissing('manager')->loadCount(['employees', 'teams']);

        return $this->success(new DepartmentResource($department), 'Department created.', 201);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $this->authorize('departments.manage');

        $department->update([
            'name'        => $request->validated('name'),
            'code'        => strtoupper($request->validated('code')),
            'description' => $request->validated('description'),
            'manager_id'  => $request->validated('manager_id'),
            'is_active'   => (bool) $request->validated('is_active', $department->is_active),
        ]);

        $department = $department->fresh();
        $department->loadMissing('manager')->loadCount(['employees', 'teams']);

        return $this->success(new DepartmentResource($department), 'Department updated.');
    }

    public function destroy(Request $request, Department $department): JsonResponse
    {
        $this->authorize('departments.manage');

        if ($department->employees()->exists() || $department->teams()->exists()) {
            return $this->error('This department still has employees or teams. Move them to another department first.', 409);
        }

        $department->delete();

        return $this->success(message: 'Department deleted.');
    }
}