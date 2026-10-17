<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\DocumentFolder
 */
class DocumentFolderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->uuid,
            'name'       => $this->name,
            'parent_id'  => $this->parent_id !== null && $this->relationLoaded('parent') && $this->parent !== null
                ? $this->parent->uuid
                : null,

            'department' => $this->whenLoaded('department', fn () => $this->department !== null ? [
                'id'   => $this->department->uuid,
                'name' => $this->department->name,
            ] : null),

            'documents_count' => $this->whenCountLoaded('documents'),
            'created_at'      => $this->created_at?->toISOString(),
        ];
    }
}