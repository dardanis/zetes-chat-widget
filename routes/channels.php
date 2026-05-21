<?php

use App\Models\ChatSession;
use App\Services\Rag\ProjectAccessService;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('project.{projectId}.chat.{chatSessionId}', function ($user, string|int $projectId, string|int $chatSessionId): bool {
    $resolvedProject = app(ProjectAccessService::class)->resolveProjectForUser($user, (int) $projectId);

    return ChatSession::query()
        ->where('tenant_id', $resolvedProject->tenant_id)
        ->where('project_id', $resolvedProject->id)
        ->whereKey((int) $chatSessionId)
        ->exists();
});

