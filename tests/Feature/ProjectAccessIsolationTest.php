<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectAccessIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_documents_of_project_from_another_tenant(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $tenantA = Tenant::query()->create(['name' => 'Tenant A']);
        $tenantB = Tenant::query()->create(['name' => 'Tenant B']);

        $tenantA->users()->attach($owner->id, ['role' => 'owner']);
        $tenantB->users()->attach($intruder->id, ['role' => 'owner']);

        $project = Project::query()->create([
            'tenant_id' => $tenantA->id,
            'owner_id' => $owner->id,
            'name' => 'Secured Project',
            'slug' => 'secured-project',
            'widget_key' => 'widget-key-'.str_repeat('a', 25),
        ]);

        $this->actingAs($intruder)
            ->getJson('/api/projects/'.$project->id.'/documents')
            ->assertNotFound();
    }
}

