<?php

namespace App\Support;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * JSON shapes for chat. Callers must eager-load the relations noted below
 * (lazy loading is disabled outside production).
 */
final class ChatPayload
{
    /**
     * Needs: user.employee, replyTo.user, conversation (for the uuid).
     *
     * @return array<string, mixed>
     */
    public static function message(Message $message): array
    {
        $reply = $message->reply_to_id !== null ? $message->replyTo : null;

        return [
            'id'              => $message->uuid,
            'conversation_id' => $message->conversation->uuid,
            'body'            => $message->body,
            'sender'          => PersonPayload::make($message->user),
            'reply_to'        => $reply === null ? null : [
                'id'      => $reply->uuid,
                'excerpt' => $reply->trashed() ? null : Str::limit($reply->body, 90),
                'sender'  => $reply->user?->name,
            ],
            'edited_at'  => $message->edited_at?->toISOString(),
            'created_at' => $message->created_at?->toISOString(),
        ];
    }

    /**
     * Needs: team, project, participants.user.employee, latestMessage.user.
     *
     * @return array<string, mixed>
     */
    public static function conversation(Conversation $conversation, User $viewer, int $unread = 0): array
    {
        $others = $conversation->participants
            ->filter(fn ($p) => $p->user_id !== $viewer->id)
            ->map(fn ($p) => $p->user)
            ->filter();

        $name = $conversation->type === 'direct'
            ? ($others->first()?->name ?? 'Unknown')
            : ($conversation->name ?? 'Conversation');

        $last = $conversation->latestMessage;
        $mine = $conversation->participants->firstWhere('user_id', $viewer->id);

        return [
            'id'          => $conversation->uuid,
            'type'        => $conversation->type,
            'name'        => $name,
            'team'        => $conversation->team ? ['id' => $conversation->team->uuid, 'name' => $conversation->team->name] : null,
            'project'     => $conversation->project ? ['id' => $conversation->project->uuid, 'name' => $conversation->project->name] : null,
            'participants_count' => $conversation->participants->count(),
            'members'     => $conversation->type === 'direct'
                ? [PersonPayload::make($others->first())]
                : $others->take(4)->map(fn ($u) => PersonPayload::make($u))->values()->all(),
            'last_message' => $last === null ? null : [
                'body'       => Str::limit($last->body, 80),
                'sender'     => $last->user?->name,
                'created_at' => $last->created_at?->toISOString(),
            ],
            'last_message_at' => $conversation->last_message_at?->toISOString(),
            'unread_count'    => $unread,
            'muted'           => (bool) ($mine?->muted),
        ];
    }
}
