<?php

namespace App\Http\Resources\V1;

use App\Support\PersonPayload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\DesignVersion
 */
class DesignVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->uuid,
            'label'         => $this->label,
            'note'          => $this->note,
            'progress'      => $this->progress,
            'status'        => $this->status,
            'author'        => $this->whenLoaded('user', fn () => PersonPayload::make($this->user)),
            'external_author' => $this->external_author,
            'imported'      => $this->external_version_id !== null,
            'thumbnail_url' => $this->thumbnail_url,
            'committed_at'  => $this->committed_at?->toISOString(),
        ];
    }
}
