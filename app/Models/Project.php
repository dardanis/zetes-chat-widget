<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = ['tenant_id', 'owner_id', 'name', 'slug', 'widget_key', 'widget_secret', 'widget_secret_hash'];

    protected $hidden = ['widget_secret_hash'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }

    public function chats(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }

    public function confluenceSpaces(): HasMany
    {
        return $this->hasMany(ProjectConfluenceSpace::class);
    }
}
