<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AtlassianConnection extends Model
{
    protected $fillable = [
        'tenant_id',
        'created_by',
        'base_url',
        'email',
        'api_token',
        'cloud_id',
        'is_active',
        'metadata',
    ];

    protected $hidden = ['api_token'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
            'api_token' => 'encrypted',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function projectSpaces(): HasMany
    {
        return $this->hasMany(ProjectConfluenceSpace::class);
    }
}

