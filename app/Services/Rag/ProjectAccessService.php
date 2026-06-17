<?php

namespace App\Services\Rag;

use App\Models\Project;
use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ProjectAccessService
{
    public function __construct(private readonly AccessControlService $access) {}

    public function resolveProjectForUser(User $user, int $projectId): Project
    {
        $project = Project::query()->with('tenant')->whereKey($projectId)->first();

        if (! $project) {
            throw (new ModelNotFoundException)->setModel(Project::class);
        }

        if (! $this->access->canAccessProject($user, $project)) {
            throw (new ModelNotFoundException)->setModel(Project::class);
        }

        return $project;
    }
}
