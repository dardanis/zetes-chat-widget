<?php

namespace App\Services\Rag;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProjectAccessService
{
    public function resolveProjectForUser(User $user, int $projectId): Project
    {
        $project = Project::query()->whereKey($projectId)->first();

        if (! $project) {
            throw (new ModelNotFoundException())->setModel(Project::class);
        }

        $isTenantMember = $user->tenants()->whereKey($project->tenant_id)->exists();

        if (! $isTenantMember) {
            throw (new ModelNotFoundException())->setModel(Project::class);
        }

        return $project;
    }
}

