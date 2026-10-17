<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ProjectActivityResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectActivityController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $activities = $project->activities()
            ->with('user.employee')
            ->paginate(min((int) $request->query('per_page', 15), 50));

        return $this->paginated($activities, ProjectActivityResource::class);
    }
}