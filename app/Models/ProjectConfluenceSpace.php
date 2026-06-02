<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectConfluenceSpace extends Model
{
    protected $fillable = [
        'tenant_id',
        'project_id',
        'atlassian_connection_id',
        'selected_by',
        'space_id',
        'space_key',
        'space_name',
        'space_type',
        'is_enabled',
        'last_synced_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'last_synced_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(AtlassianConnection::class, 'atlassian_connection_id');
    }

    public function selector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_by');
    }
}
