<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\StoreGrantRequest;
use App\Http\Requests\Documents\UpdateGrantRequest;
use App\Http\Resources\V1\DocumentGrantResource;
use App\Models\Document;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\Documents\DocumentSharedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentGrantController extends Controller
{
    public function store(StoreGrantRequest $request, Document $document): JsonResponse
    {
        $this->authorize('manageGrants', $document);

        $target = User::query()->where('uuid', $request->validated('user_id'))->firstOrFail();
        $ability = (string) $request->validated('ability');

        if ($document->grants()->where('user_id', $target->id)->exists()) {
            return $this->error('This document is already shared with that user.', 409);
        }

        // The owner never needs a grant.
        $ownerUserId = $document->owner_id !== null
            ? Employee::query()->where('id', $document->owner_id)->value('user_id')
            : null;

        if ($ownerUserId === $target->id) {
            return $this->error('The owner already has full access to this document.', 409);
        }

        $document->grants()->create([
            'user_id'    => $target->id,
            'ability'    => $ability,
            'granted_by' => $request->user()->id,
        ]);

        if ($target->id !== $request->user()->id) {
            $target->notify(new DocumentSharedNotification($document, $request->user(), $ability));
        }

        return $this->success(
            DocumentGrantResource::collection(
                $document->fresh()->grants()->with(['user', 'grantedBy'])->get(),
            )->resolve(),
            'Access granted.',
            201,
        );
    }

    public function update(UpdateGrantRequest $request, Document $document, User $user): JsonResponse
    {
        $this->authorize('manageGrants', $document);

        $grant = $document->grants()->where('user_id', $user->id)->first();

        if ($grant === null) {
            return $this->error('This document is not shared with that user.', 404);
        }

        $grant->update(['ability' => (string) $request->validated('ability')]);

        return $this->success(
            DocumentGrantResource::collection(
                $document->fresh()->grants()->with(['user', 'grantedBy'])->get(),
            )->resolve(),
            'Access level updated.',
        );
    }

    public function destroy(Request $request, Document $document, User $user): JsonResponse
    {
        $this->authorize('manageGrants', $document);

        $deleted = $document->grants()->where('user_id', $user->id)->delete();

        if ($deleted === 0) {
            return $this->error('This document is not shared with that user.', 404);
        }

        return $this->success(
            DocumentGrantResource::collection(
                $document->fresh()->grants()->with(['user', 'grantedBy'])->get(),
            )->resolve(),
            'Access revoked.',
        );
    }
}