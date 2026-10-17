<?php

namespace App\Domain\Documents;

use App\Models\Document;
use App\Models\Project;
use App\Models\User;

final class DocumentAccess
{
    public function canView(User $user, Document $document): bool
    {
        if (! $user->can('documents.view')) {
            return false;
        }

        if ($user->can('documents.manage')) {
            return true;
        }

        if ($document->grants()->where('user_id', $user->id)->exists()) {
            return true;
        }

        if ($document->created_by === $user->id) {
            return true;
        }

        $employee = $user->employee()->first();

        if ($employee !== null) {
            if ($document->owner_id === $employee->id) {
                return true;
            }

            if ($employee->department_id !== null && $document->department_id === $employee->department_id) {
                return true;
            }
        }

        if ($document->project_id !== null) {
            return Project::query()
                ->where('id', $document->project_id)
                ->accessibleBy($user)
                ->exists();
        }

        return false;
    }

    public function canEdit(User $user, Document $document): bool
    {
        if (! $user->can('documents.view')) {
            return false;
        }

        if ($user->can('documents.manage')) {
            return true;
        }

        $employee = $user->employee()->first();

        if ($employee !== null && $document->owner_id === $employee->id) {
            return true;
        }

        return $document->grants()
            ->where('user_id', $user->id)
            ->where('ability', 'edit')
            ->exists();
    }

    public function canDelete(User $user, Document $document): bool
    {
        if ($user->can('documents.manage')) {
            return true;
        }

        $employee = $user->employee()->first();

        return $employee !== null && $document->owner_id === $employee->id;
    }
}