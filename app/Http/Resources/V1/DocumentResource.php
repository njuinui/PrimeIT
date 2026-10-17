<?php

namespace App\Http\Resources\V1;

use App\Domain\Documents\DocumentAccess;
use App\Http\Resources\V1\DocumentGrantResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Document
 */
class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $access = app(DocumentAccess::class);
        $user   = $request->user();

        return [
            'id'              => $this->uuid,
            'title'           => $this->title,
            'description'     => $this->description,
            'file_name'       => $this->file_name,
            'mime_type'       => $this->mime_type,
            'size_bytes'      => (int) $this->size_bytes,
            'current_version' => (int) $this->current_version,
            'status'          => $this->status,
            'is_locked'       => $this->status === 'locked',
            'tags'            => $this->tags ?? [],
            'created_at'      => $this->created_at?->toISOString(),
            'updated_at'      => $this->updated_at?->toISOString(),

            'folder' => $this->whenLoaded('folder', fn () => $this->folder !== null ? [
                'id'   => $this->folder->uuid,
                'name' => $this->folder->name,
            ] : null),

            'project' => $this->whenLoaded('project', fn () => $this->project !== null ? [
                'id'   => $this->project->uuid,
                'name' => $this->project->name,
            ] : null),

            'department' => $this->whenLoaded('department', fn () => $this->department !== null ? [
                'id'   => $this->department->uuid,
                'name' => $this->department->name,
            ] : null),

            'owner' => $this->whenLoaded('owner', fn () => $this->owner !== null ? [
                'id'        => $this->owner->uuid,
                'full_name' => trim($this->owner->first_name.' '.$this->owner->last_name),
            ] : null),

            'versions' => $this->whenLoaded(
                'versions',
                fn () => DocumentVersionResource::collection($this->versions)->resolve(),
            ),

            'grants' => $this->whenLoaded(
                'grants',
                fn () => DocumentGrantResource::collection($this->grants)->resolve(),
            ),

            'can_edit'   => $user !== null ? $access->canEdit($user, $this->resource) : false,
            'can_delete' => $user !== null ? $access->canDelete($user, $this->resource) : false,
            'can_share'  => $user !== null ? $access->canEdit($user, $this->resource) : false,
        ];
    }
}