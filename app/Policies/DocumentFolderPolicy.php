<?php

namespace App\Policies;

use App\Models\DocumentFolder;
use App\Models\User;

class DocumentFolderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('documents.view');
    }

    public function create(User $user): bool
    {
        return $user->can('documents.manage');
    }

    public function update(User $user, DocumentFolder $folder): bool
    {
        return $user->can('documents.manage');
    }

    public function delete(User $user, DocumentFolder $folder): bool
    {
        return $user->can('documents.manage');
    }
}