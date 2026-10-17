<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Design\FigmaSync;
use App\Domain\Design\RecordDesignVersion;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\DesignFileResource;
use App\Http\Resources\V1\DesignVersionResource;
use App\Models\DesignFile;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class DesignFileController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        abort_unless($request->user()->can('design.view'), 403);

        $request->attributes->set('design.can_manage', $request->user()->can('create', [DesignFile::class, $project]));

        $files = DesignFile::query()
            ->where('project_id', $project->id)
            ->with(['owner.employee', 'latestVersion.user.employee', 'task:id,uuid,task_number,title'])
            ->withCount('versions')
            ->orderByRaw('last_activity_at IS NULL')
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->get();

        return $this->success(DesignFileResource::collection($files)->resolve($request));
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('create', [DesignFile::class, $project]);

        $data = $request->validate([
            'title'    => ['required', 'string', 'max:255'],
            'url'      => ['required', 'url', 'max:2000'],
            'provider' => ['required', Rule::in(DesignFile::PROVIDERS)],
            'task_id'  => ['nullable', 'uuid'],
            'status'   => ['nullable', Rule::in(DesignFile::STATUSES)],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $key = $data['provider'] === 'figma' ? DesignFile::figmaKeyFromUrl($data['url']) : null;

        if ($data['provider'] === 'figma' && $key === null) {
            throw ValidationException::withMessages(['url' => ['That does not look like a Figma file link.']]);
        }

        if ($key !== null && DesignFile::query()->where('project_id', $project->id)->where('provider', 'figma')->where('external_key', $key)->exists()) {
            throw ValidationException::withMessages(['url' => ['This Figma file is already tracked on the project.']]);
        }

        $task = ! empty($data['task_id'])
            ? Task::query()->where('uuid', $data['task_id'])->where('project_id', $project->id)->first()
            : null;

        $file = DesignFile::create([
            'project_id'   => $project->id,
            'task_id'      => $task?->id,
            'owner_id'     => $request->user()->id,
            'provider'     => $data['provider'],
            'title'        => $data['title'],
            'url'          => $data['url'],
            'external_key' => $key,
            'status'       => $data['status'] ?? 'draft',
            'progress'     => $data['progress'] ?? 0,
            'last_activity_at' => now(),
        ]);

        ProjectActivity::record($project, $request->user(), 'design_added', 'started tracking design "' . $file->title . '"');

        $request->attributes->set('design.can_manage', true);

        return $this->success(
            new DesignFileResource($file->load(['owner.employee', 'project:id,uuid'])->loadCount('versions')),
            'Design file added.',
            201,
        );
    }

    public function show(Request $request, DesignFile $designFile): JsonResponse
    {
        $this->authorize('view', $designFile);

        $request->attributes->set('design.can_manage', $request->user()->can('manage', $designFile));

        $designFile->load(['owner.employee', 'project:id,uuid', 'versions.user.employee', 'task:id,uuid,task_number,title'])
            ->loadCount('versions');

        return $this->success(new DesignFileResource($designFile));
    }

    public function update(Request $request, DesignFile $designFile): JsonResponse
    {
        $this->authorize('manage', $designFile);

        $data = $request->validate([
            'title'    => ['sometimes', 'required', 'string', 'max:255'],
            'status'   => ['sometimes', Rule::in(DesignFile::STATUSES)],
            'progress' => ['sometimes', 'integer', 'min:0', 'max:100'],
        ]);

        $designFile->fill($data)->save();

        $request->attributes->set('design.can_manage', true);

        return $this->success(
            new DesignFileResource($designFile->load(['owner.employee', 'project:id,uuid'])->loadCount('versions')),
            'Design file updated.',
        );
    }

    public function destroy(Request $request, DesignFile $designFile): JsonResponse
    {
        $this->authorize('manage', $designFile);

        $designFile->delete();

        return $this->success(null, 'Design file removed.');
    }

    /** Log a version by hand — used for Adobe XD, Sketch and anything without a feed. */
    public function storeVersion(Request $request, DesignFile $designFile, RecordDesignVersion $record): JsonResponse
    {
        $this->authorize('manage', $designFile);

        $data = $request->validate([
            'label'    => ['required', 'string', 'max:120'],
            'note'     => ['nullable', 'string', 'max:5000'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'status'   => ['nullable', Rule::in(DesignFile::STATUSES)],
        ]);

        $version = $record($designFile, $request->user(), $data)->load('user.employee');

        return $this->success(new DesignVersionResource($version), 'Version logged.', 201);
    }

    /** Pull new saved versions from Figma. */
    public function sync(Request $request, DesignFile $designFile, FigmaSync $figma): JsonResponse
    {
        $this->authorize('manage', $designFile);

        if ($designFile->provider !== 'figma' || $designFile->external_key === null) {
            return $this->error('Only Figma files can be synced automatically.', 422);
        }

        if (! $figma->configured()) {
            return $this->error('Figma sync is not configured on the server (FIGMA_TOKEN).', 422);
        }

        try {
            $imported = $figma->sync($designFile);
        } catch (Throwable $e) {
            report($e);

            return $this->error('Figma could not be reached or refused access to this file.', 502);
        }

        return $this->success(['imported' => $imported], $imported === 0 ? 'Already up to date.' : "{$imported} new version(s) imported.");
    }
}
