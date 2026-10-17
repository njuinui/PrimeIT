<?php

namespace App\Domain\Documents;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Notifications\Documents\DocumentNewVersionNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

final class UploadDocumentVersion
{
    public function __invoke(Document $document, UploadedFile $file, ?string $note, User $actor): DocumentVersion
    {
        $next = (int) $document->versions()->max('version_number') + 1;

        $path = $file->store("documents/{$document->uuid}/v{$next}", 'local');

        $version = $document->versions()->create([
            'version_number' => $next,
            'file_path'      => $path,
            'file_name'      => $file->getClientOriginalName(),
            'mime_type'      => $file->getClientMimeType(),
            'size_bytes'     => $file->getSize() ?? 0,
            'note'           => $note,
            'uploaded_by'    => $actor->id,
        ]);

        $document->forceFill([
            'current_version' => $next,
            'file_path'       => $path,
            'file_name'       => $file->getClientOriginalName(),
            'mime_type'       => $file->getClientMimeType(),
            'size_bytes'      => $file->getSize() ?? 0,
        ])->save();

        $this->notifyStakeholders($document->fresh(), $version, $actor);

        return $version;
    }

    /** Grant holders + owner account + creator, minus the actor. */
    private function notifyStakeholders(Document $document, DocumentVersion $version, User $actor): void
    {
        $userIds = new Collection(
            $document->grants()->pluck('user_id')->all(),
        );

        $ownerUserId = $document->owner_id !== null
            ? \App\Models\Employee::query()->where('id', $document->owner_id)->value('user_id')
            : null;

        $userIds = $userIds
            ->merge([$ownerUserId, $document->created_by])
            ->filter()
            ->unique()
            ->reject(fn ($id) => $id === $actor->id)
            ->values();

        if ($userIds->isEmpty()) {
            return;
        }

        User::query()->whereIn('id', $userIds->all())->get()->each(
            fn (User $user) => $user->notify(
                new DocumentNewVersionNotification($document, $version, $actor),
            ),
        );
    }
}