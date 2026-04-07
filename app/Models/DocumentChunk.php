<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    protected $fillable = [
        'tenant_id',
        'project_id',
        'project_document_id',
        'chunk_index',
        'page_from',
        'page_to',
        'content',
        'content_tokens',
        'embedding',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'content_tokens' => 'array',
            'embedding' => 'array',
            'metadata' => 'array',
        ];
    }

    public function projectDocument(): BelongsTo
    {
        return $this->belongsTo(ProjectDocument::class);
    }
}

