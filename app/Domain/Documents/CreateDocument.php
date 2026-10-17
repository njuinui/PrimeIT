<?php

namespace App\Domain\Documents;

use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;

final class CreateDocument
{
    /**
     * @param array<string, mixed> $data validated metadata (folder/project/owner/department/tags…)
     */
    public function __invoke(array $data, UploadedFile $file, User $actor): Document
    {
        $path = $file->store('documents/'.str()->uuid()->toString().'/v1', 'local');

        $document = Document::create([
            ...$data,
            'file_name'      => $file->getClientOriginalName(),
            'file_path'      => $path,
            'mime_type'      => $file->getClientMimeType(),
            'size_bytes'     => $file->getSize() ?? 0,
            'current_version' => 1,
            'status'         => 'active',
            'created_by'     => $actor->id,
        ]);

        $document->versions()->create([
            'version_number' => 1,
            'file_path'      => $path,
            'file_name'      => $file->getClientOriginalName(),
            'mime_type'      => $file->getClientMimeType(),
            'size_bytes'     => $file->getSize() ?? 0,
            'note'           => 'Initial version',
            'uploaded_by'    => $actor->id,
        ]);

        return $document;
    }
}