<?php

namespace App\Http\Resources\V1;

use App\Support\PersonPayload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\WorkUpdate
 */
class WorkUpdateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->uuid,
            'kind'          => $this->kind,
            'source'        => $this->source,
            'message'       => $this->message,
            'body'          => $this->body,
            'minutes_spent' => $this->minutes_spent,
            'progress_from' => $this->progress_from,
            'progress_to'   => $this->progress_to,
            'author'        => $this->whenLoaded('user', fn () => PersonPayload::make($this->user)),
            'task'          => $this->whenLoaded('task', fn () => $this->task ? [
                'id'          => $this->task->uuid,
                'task_number' => $this->task->task_number,
                'title'       => $this->task->title,
            ] : null),
            'design_file'   => $this->whenLoaded('designFile', fn () => $this->designFile ? [
                'id'       => $this->designFile->uuid,
                'title'    => $this->designFile->title,
                'provider' => $this->designFile->provider,
            ] : null),
            'committed_at'  => $this->committed_at?->toISOString(),
        ];
    }
}
