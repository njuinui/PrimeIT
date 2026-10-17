<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\StoreFolderRequest;
use App\Http\Requests\Documents\UpdateFolderRequest;
use App\Http\Resources\V1\DocumentFolderResource;
use App\Models\DocumentFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentFolderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DocumentFolder::class);

        $folders = DocumentFolder::query()
            ->accessibleBy($request->user())
            ->with('parent')
            ->withCount('documents')
            ->orderBy('name')
            ->get();

        return $this->success(DocumentFolderResource::collection($folders)->resolve());
    }

    public function store(StoreFolderRequest $request): JsonResponse
    {
        $this->authorize('create', DocumentFolder::class);

        $folder = DocumentFolder::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return $this->success(
            new DocumentFolderResource($folder->loadMissing('parent')->loadCount('documents')),
            'Folder created.',
            201,
        );
    }

    public function update(UpdateFolderRequest $request, DocumentFolder $folder): JsonResponse
    {
        $this->authorize('update', $folder);

        if ($request->filled('parent_id')
            && $this->wouldCreateCycle($folder, (int) $request->validated('parent_id'))) {
            return $this->error('A folder cannot be moved inside itself or its descendants.', 422);
        }

        $folder->update($request->validated());

        return $this->success(
            new DocumentFolderResource($folder->fresh()->loadMissing('parent')->loadCount('documents')),
            'Folder updated.',
        );
    }

    public function destroy(Request $request, DocumentFolder $folder): JsonResponse
    {
        $this->authorize('delete', $folder);

        if ($folder->children()->exists() || $folder->documents()->exists()) {
            return $this->error('This folder is not empty — move its contents first.', 409);
        }

        $folder->delete();

        return $this->success(message: 'Folder deleted.');
    }

    private function wouldCreateCycle(DocumentFolder $folder, int $newParentId): bool
    {
        $cursor = $newParentId;

        while ($cursor !== null) {
            if ($cursor === $folder->id) {
                return true;
            }

            $cursor = DocumentFolder::query()->where('id', $cursor)->value('parent_id');
        }

        return false;
    }
}