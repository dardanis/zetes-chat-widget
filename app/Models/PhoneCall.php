<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhoneCall extends Model
{
    protected $fillable = [
        'tenant_id',
        'project_id',
        'chat_session_id',
        'call_sid',
        'from_number',
        'to_number',
        'from_country',
        'from_city',
        'status',
        'direction',
        'turn_count',
        'duration_seconds',
        'recording_url',
        'started_at',
        'ended_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'turn_count' => 'integer',
            'duration_seconds' => 'integer',
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

    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'no-answer', 'busy', 'canceled'], true);
    }
}
