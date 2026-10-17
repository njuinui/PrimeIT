<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\Meeting
 */
class MeetingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        $myResponse = null;
        $isParticipant = false;

        if ($user !== null && $this->relationLoaded('participants')) {
            $mine = $this->participants->first(fn ($participant) => $participant->user_id === $user->id);

            $isParticipant = $mine !== null;
            $myResponse   = $mine?->pivot->response ?? null;
        }

        return [
            'id'          => $this->uuid,
            'title'       => $this->title,
            'description' => $this->description,
            'location'    => $this->location,
            'video_link'  => $this->video_link,
            'starts_at'   => $this->starts_at->toISOString(),
            'ends_at'     => $this->ends_at->toISOString(),
            'status'      => $this->status,

            'is_upcoming' => $this->status === 'scheduled' && $this->starts_at->isFuture(),
            'is_happening' => $this->isHappening(),

            'organizer' => $this->whenLoaded('organizer', fn () => $this->organizer !== null ? [
                'id'        => $this->organizer->uuid,
                'full_name' => trim($this->organizer->first_name.' '.$this->organizer->last_name),
                'job_title' => $this->organizer->job_title,
                'avatar_url' => $this->organizer->avatar ? Storage::disk('public')->url($this->organizer->avatar) : null,
            ] : null),

            'participants' => $this->whenLoaded('participants', fn () => $this->participants->map(fn ($participant) => [
                'id'           => $participant->uuid,
                'full_name'    => trim($participant->first_name.' '.$participant->last_name),
                'job_title'    => $participant->job_title,
                'avatar_url'   => $participant->avatar ? Storage::disk('public')->url($participant->avatar) : null,
                'attendance'   => $participant->pivot->attendance,
                'response'     => $participant->pivot->response,
                'responded_at' => $participant->pivot->responded_at?->toISOString(),
            ])->values()->all()),

            'participants_count' => $this->when(
                $this->relationLoaded('participants'),
                fn () => $this->participants->count(),
            ),

            'minutes' => $this->whenLoaded('minutes', fn () => $this->minutes !== null ? [
                'content'      => $this->minutes->content,
                'action_items' => ($this->minutes->action_items ?? []) ?: [],
                'published_at' => $this->minutes->published_at?->toISOString(),
                'updated_at'   => $this->minutes->updated_at?->toISOString(),
            ] : null),

            'my_response' => $myResponse,
            'is_participant' => $isParticipant,

            'can_manage' => $user?->can('meetings.manage') ?? false,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}