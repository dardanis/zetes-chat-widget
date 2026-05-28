<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectDocument extends Model
{
    protected $fillable = [
        'tenant_id',
        'project_id',
        'uploaded_by',
        'original_name',
        'storage_path',
        'mime_type',
        'file_size',
        'status',
        'ingestion_type',
        'source_url',
        'metadata',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }
}

