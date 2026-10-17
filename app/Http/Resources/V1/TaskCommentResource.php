<?php

namespace App\Http\Resources\V1;

use App\Support\UserAvatar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TaskComment
 */
class TaskCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->uuid,
            'body'       => $this->body,
            'mentions'   => $this->mentions,
            'edited_at'  => $this->edited_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),

            'author' => $this->whenLoaded('user', fn () => $this->user !== null ? [
                'id'   => $this->user->uuid,
                'name' => $this->user->name,
                'avatar_url' => UserAvatar::url($this->user),
            ] : null),

            'can_edit'   => $request->user()?->id === $this->user_id,
            'can_delete' => $request->user()?->id === $this->user_id,
        ];
    }
}