<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Documents\CreateDocument;
use App\Domain\Documents\UploadDocumentVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\StoreDocumentRequest;
use App\Http\Requests\Documents\StoreDocumentVersionRequest;
use App\Http\Requests\Documents\UpdateDocumentRequest;
use App\Http\Resources\V1\DocumentResource;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    private const RELATIONS = ['folder', 'project', 'department', 'owner', 'creator'];

    public function __construct(
        private readonly CreateDocument $createDocument,
        private readonly UploadDocumentVersion $uploadVersion,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('documents.view');

        $user    = $request->user();
        $search  = trim((string) $request->query('search'));
        $tag     = trim((string) $request->query('tag'));
        $status  = (string) $request->query('status');
        $folder  = $this->uuidToId(DocumentFolder::class, (string) $request->query('folder'));

        $documents = Document::query()
            ->accessibleBy($user)
            ->with(self::RELATIONS)
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('title', 'like', "%{$search}%")
                ->orWhere('file_name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")))
            ->when($tag !== '', fn ($q) => $q->where('tags', 'like', '%'.$tag.'%'))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($folder !== null, fn ($q) => $q->whereIn('folder_id', $this->folderDescendantIds($folder)))
            ->when($request->boolean('mine'), function ($q) use ($user): void {
                $employee = $user->employee()->first();

                $q->where(function ($inner) use ($user, $employee): void {
                    $inner->where('created_by', $user->id);

                    if ($employee !== null) {
                        $inner->orWhere('owner_id', $employee->id);
                    }
                });
            })
            ->when($request->boolean('shared'), fn ($q) => $q->whereHas(
                'grants',
                fn ($g) => $g->where('document_grants.user_id', $user->id),
            ))
            ->orderByDesc('updated_at')
            ->paginate(min((int) $request->query('per_page', 15), 100));

        return $this->paginated($documents, DocumentResource::class);
    }

    public function store(StoreDocumentRequest $request): JsonResponse
    {
        $this->authorize('create', Document::class);

        $user     = $request->user();
        $employee = $user->employee()->first();

        $data = collect($request->validated())->except('file')->all();

        // Department derives from the folder, else the project, else the uploader.
        $data['department_id'] ??= $data['folder_id'] !== null
            ? DocumentFolder::query()->where('id', $data['folder_id'])->value('department_id')
            : null;

        $data['department_id'] ??= $data['project_id'] !== null
            ? Project::query()->where('id', $data['project_id'])->value('department_id')
            : null;

        $data['department_id'] ??= $employee?->department_id;
        $data['owner_id'] ??= $employee?->id;

        $document = ($this->createDocument)($data, $request->file('file'), $user);

        return $this->success(
            new DocumentResource($document->loadMissing(self::RELATIONS)),
            'Document uploaded.',
            201,
        );
    }

    public function show(Request $request, Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        $document->loadMissing([...self::RELATIONS, 'versions.uploader', 'grants.user', 'grants.grantedBy']);

        return $this->success(new DocumentResource($document));
    }

    public function update(UpdateDocumentRequest $request, Document $document): JsonResponse
    {
        $this->authorize('update', $document);

        $document->update(collect($request->validated())->all());

        return $this->success(
            new DocumentResource($document->fresh()->loadMissing(self::RELATIONS)),
            'Document updated.',
        );
    }

    public function destroy(Request $request, Document $document): JsonResponse
    {
        $this->authorize('delete', $document);

        $document->delete();

        return $this->success(message: 'Document deleted.');
    }

    public function download(Request $request, Document $document): mixed
    {
        $this->authorize('view', $document);

        $versionNumber = $request->query('version');

        $version = $versionNumber !== null
            ? $document->versions()->where('version_number', (int) $versionNumber)->first()
            : $document->versions()->where('version_number', $document->current_version)->first();

        if ($version === null || ! Storage::disk('local')->exists($version->file_path)) {
            return $this->error('The requested version is not available.', 404);
        }

        return Storage::disk('local')->download($version->file_path, $version->file_name);
    }

    public function storeVersion(StoreDocumentVersionRequest $request, Document $document): JsonResponse
    {
        $this->authorize('update', $document);

        if ($document->status === 'locked' && ! $request->user()->can('documents.manage')) {
            return $this->error('This document is locked — new versions are rejected.', 409);
        }

        $version = ($this->uploadVersion)(
            $document,
            $request->file('file'),
            $request->validated('note'),
            $request->user(),
        );

        return $this->success(
            new DocumentResource($document->fresh()->loadMissing([...self::RELATIONS, 'versions.uploader'])),
            sprintf('Version %d uploaded.', $version->version_number),
            201,
        );
    }

    public function lock(Request $request, Document $document): JsonResponse
    {
        return $this->toggleLock($request, $document, 'locked');
    }

    public function unlock(Request $request, Document $document): JsonResponse
    {
        return $this->toggleLock($request, $document, 'active');
    }

    // ── Internals ─────────────────────────────────────────────

    private function toggleLock(Request $request, Document $document, string $status): JsonResponse
    {
        $this->authorize('update', $document);

        $document->forceFill(['status' => $status])->save();

        return $this->success(
            new DocumentResource($document->fresh()->loadMissing(self::RELATIONS)),
            $status === 'locked' ? 'Document locked.' : 'Document unlocked.',
        );
    }

    /** Folder + all descendant folder ids (recursive browsing). */
    private function folderDescendantIds(int $folderId): array
    {
        $ids      = [$folderId];
        $frontier = [$folderId];

        while ($frontier !== []) {
            $next = DocumentFolder::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->diff($ids)
                ->values()
                ->all();

            $ids      = array_merge($ids, $next);
            $frontier = $next;
        }

        return $ids;
    }

    private function uuidToId(string $model, string $uuid): ?int
    {
        if ($uuid === '') {
            return null;
        }

        return $model::query()->where('uuid', $uuid)->value('id');
    }
}