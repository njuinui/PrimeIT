<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\DocumentVersion
 */
class DocumentVersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->uuid,
            'version_number' => (int) $this->version_number,
            'file_name'     => $this->file_name,
            'mime_type'     => $this->mime_type,
            'size_bytes'    => (int) $this->size_bytes,
            'note'          => $this->note,
            'uploaded_by'   => $this->whenLoaded('uploader', fn () => $this->uploader?->name),
            'created_at'    => $this->created_at?->toISOString(),
        ];
    }
}