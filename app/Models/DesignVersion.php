<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignVersion extends Model
{
    use HasPublicUuid;

    protected $fillable = [
        'design_file_id', 'user_id', 'label', 'note', 'progress', 'status',
        'external_version_id', 'external_author', 'thumbnail_url', 'committed_at',
    ];

    protected function casts(): array
    {
        return [
            'progress'     => 'integer',
            'committed_at' => 'datetime',
        ];
    }

    public function designFile(): BelongsTo
    {
        return $this->belongsTo(DesignFile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
