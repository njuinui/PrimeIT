<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    use HasFactory, HasPublicUuid;

    protected $fillable = [
        'document_id', 'version_number', 'file_path', 'file_name',
        'mime_type', 'size_bytes', 'note', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes'    => 'integer',
            'version_number' => 'integer',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}