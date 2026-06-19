<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessageFeedback extends Model
{
    protected $table = 'chat_message_feedback';

    protected $fillable = [
        'tenant_id',
        'project_id',
        'chat_session_id',
        'chat_message_id',
        'user_id',
        'rating',
        'channel',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }
}
