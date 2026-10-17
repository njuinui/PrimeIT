<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Public UUID addressing for models that keep an integer primary key.
 *
 * - A UUID is generated into the `uuid` column on first create.
 * - Route model binding resolves by UUID (the int id is never exposed).
 * - The integer PK stays in place so foreign keys and joins remain cheap.
 */
trait HasPublicUuid
{
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function bootHasPublicUuid(): void
    {
        static::creating(function (self $model): void {
            if ($model->uuid === null) {
                $model->uuid = (string) Str::orderedUuid();
            }
        });
    }
}