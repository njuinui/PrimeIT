<?php

namespace App\Policies;

use App\Domain\Documents\DocumentAccess;
use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function __construct(private readonly DocumentAccess $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('documents.view');
    }

    public function view(User $user, Document $document): bool
    {
        return $this->access->canView($user, $document);
    }

    public function update(User $user, Document $document): bool
    {
        return $this->access->canEdit($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->access->canDelete($user, $document);
    }

    public function manageGrants(User $user, Document $document): bool
    {
        return $this->access->canEdit($user, $document);
    }
}