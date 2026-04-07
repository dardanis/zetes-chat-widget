<?php

namespace Tests\Feature;

use App\Models\ChatSession;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_returns_correct_counts(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['name' => 'Stats Tenant']);
        $tenant->users()->attach($user->id, ['role' => 'owner']);

        $project = Project::query()->create([
            'tenant_id' => $tenant->id,
            'owner_id' => $user->id,
            'name' => 'Stats Project',
            'slug' => 'stats-project',
            'widget_key' => 'wk-'.str_repeat('s', 30),
        ]);

        ProjectDocument::query()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'uploaded_by' => $user->id,
            'original_name' => 'doc.pdf',
            'storage_path' => 'rag/documents/doc.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'status' => 'indexed',
        ]);

        ChatSession::query()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'title' => 'Chat',
            'channel' => 'dashboard',
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/stats');

        $response->assertOk()
            ->assertJsonPath('data.total_tenants', 1)
            ->assertJsonPath('data.total_projects', 1)
            ->assertJsonPath('data.total_documents', 1)
            ->assertJsonPath('data.total_chats', 1)
            ->assertJsonStructure([
                'data' => [
                    'total_tenants',
                    'total_projects',
                    'total_documents',
                    'total_chats',
                    'documents_by_status',
                    'recent_projects',
                ],
            ]);
    }

    public function test_stats_only_counts_own_tenants(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $tenantA = Tenant::query()->create(['name' => 'Mine']);
        $tenantB = Tenant::query()->create(['name' => 'Theirs']);

        $tenantA->users()->attach($user->id, ['role' => 'owner']);
        $tenantB->users()->attach($other->id, ['role' => 'owner']);

        Project::query()->create([
            'tenant_id' => $tenantB->id,
            'owner_id' => $other->id,
            'name' => 'Other Project',
            'slug' => 'other-project',
            'widget_key' => 'wk-'.str_repeat('o', 30),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/stats');

        $response->assertOk()
            ->assertJsonPath('data.total_tenants', 1)
            ->assertJsonPath('data.total_projects', 0);
    }

    public function test_stats_requires_authentication(): void
    {
        $this->getJson('/api/stats')
            ->assertUnauthorized();
    }
}

