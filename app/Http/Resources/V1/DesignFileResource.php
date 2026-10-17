<?php

namespace App\Http\Resources\V1;

use App\Support\PersonPayload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects `owner.employee` loaded; `latestVersion`, `versions` and `task` are optional.
 * The controller sets the request attribute `design.can_manage` once per request.
 *
 * @mixin \App\Models\DesignFile
 */
class DesignFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->uuid,
            'project_id'       => $this->whenLoaded('project', fn () => $this->project->uuid),
            'provider'         => $this->provider,
            'title'            => $this->title,
            'url'              => $this->url,
            'status'           => $this->status,
            'progress'         => $this->progress,
            'owner'            => $this->whenLoaded('owner', fn () => PersonPayload::make($this->owner)),
            'task'             => $this->whenLoaded('task', fn () => $this->task ? [
                'id' => $this->task->uuid, 'task_number' => $this->task->task_number, 'title' => $this->task->title,
            ] : null),
            'versions_count'   => $this->whenCounted('versions'),
            'latest_version'   => $this->whenLoaded('latestVersion', fn () => $this->latestVersion
                ? (new DesignVersionResource($this->latestVersion))->resolve($request)
                : null),
            'versions'         => $this->whenLoaded('versions', fn () => DesignVersionResource::collection($this->versions)->resolve($request)),
            'can_sync'         => $this->provider === 'figma' && $this->external_key !== null,
            'can_manage'       => (bool) $request->attributes->get('design.can_manage', false),
            'last_activity_at' => $this->last_activity_at?->toISOString(),
            'last_synced_at'   => $this->last_synced_at?->toISOString(),
            'created_at'       => $this->created_at?->toISOString(),
        ];
    }
}
