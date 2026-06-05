<?php

namespace Tests\Feature;

use App\Jobs\EmbedDocumentChunkJob;
use App\Jobs\SyncProjectConfluenceSpaceJob;
use App\Models\AtlassianConnection;
use App\Models\Project;
use App\Models\ProjectConfluenceSpace;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Rag\ConfluenceApiService;
use App\Services\Rag\DocumentChunkingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ConfluenceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_existing_tenant_connections(): void
    {
        [$user, $tenant] = $this->createTenantContext();

        AtlassianConnection::query()->create([
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
            'base_url' => 'https://first.atlassian.net',
            'email' => 'first@example.test',
            'api_token' => 'token-first',
            'is_active' => true,
        ]);

        AtlassianConnection::query()->create([
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
            'base_url' => 'https://second.atlassian.net',
            'email' => 'second@example.test',
            'api_token' => 'token-second',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/tenants/'.$tenant->id.'/confluence/connections');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.email', 'second@example.test');
        $response->assertJsonPath('data.1.email', 'first@example.test');
    }

    public function test_user_can_create_connection_and_list_spaces(): void
    {
        [$user, $tenant] = $this->createTenantContext();

        Http::fake([
            'https://example.atlassian.net/wiki/rest/api/space*' => Http::response([
                'results' => [
                    ['id' => '1001', 'key' => 'ENG', 'name' => 'Engineering', 'type' => 'global'],
                    ['id' => '1002', 'key' => 'OPS', 'name' => 'Operations', 'type' => 'global'],
                ],
                '_links' => [],
            ], 200),
        ]);

        $createResponse = $this->actingAs($user)->postJson('/api/tenants/'.$tenant->id.'/confluence/connections', [
            'base_url' => 'https://example.atlassian.net',
            'email' => 'owner@example.test',
            'api_token' => 'atlassian-token',
        ]);

        $createResponse->assertCreated();

        $connectionId = $createResponse->json('data.id');

        $listResponse = $this->actingAs($user)
            ->getJson('/api/tenants/'.$tenant->id.'/confluence/connections/'.$connectionId.'/spaces');

        $listResponse->assertOk();
        $listResponse->assertJsonCount(2, 'data');
        $listResponse->assertJsonPath('data.0.key', 'ENG');
        $listResponse->assertJsonPath('data.1.name', 'Operations');
    }

    public function test_user_can_paginate_and_search_confluence_spaces(): void
    {
        [$user, $tenant] = $this->createTenantContext();

        Http::fake([
            'https://example.atlassian.net/wiki/rest/api/space*' => Http::response([
                'results' => [
                    ['id' => '1001', 'key' => 'ENG', 'name' => 'Engineering', 'type' => 'global'],
                    ['id' => '1002', 'key' => 'OPS', 'name' => 'Operations', 'type' => 'global'],
                    ['id' => '1003', 'key' => 'SEC', 'name' => 'Security', 'type' => 'global'],
                ],
                '_links' => [],
            ], 200),
        ]);

        $createResponse = $this->actingAs($user)->postJson('/api/tenants/'.$tenant->id.'/confluence/connections', [
            'base_url' => 'https://example.atlassian.net',
            'email' => 'owner@example.test',
            'api_token' => 'atlassian-token',
        ]);

        $connectionId = $createResponse->json('data.id');

        $pageResponse = $this->actingAs($user)
            ->getJson('/api/tenants/'.$tenant->id.'/confluence/connections/'.$connectionId.'/spaces?per_page=2&page=2');

        $pageResponse->assertOk();
        $pageResponse->assertJsonCount(1, 'data');
        $pageResponse->assertJsonPath('meta.current_page', 2);
        $pageResponse->assertJsonPath('meta.last_page', 2);

        $searchResponse = $this->actingAs($user)
            ->getJson('/api/tenants/'.$tenant->id.'/confluence/connections/'.$connectionId.'/spaces?q=ops');

        $searchResponse->assertOk();
        $searchResponse->assertJsonCount(1, 'data');
        $searchResponse->assertJsonPath('data.0.key', 'OPS');
    }

    public function test_user_can_select_spaces_and_queue_project_sync(): void
    {
        Queue::fake();

        [$user, $tenant] = $this->createTenantContext();
        $project = $this->createProject($tenant, $user);

        $connection = AtlassianConnection::query()->create([
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
            'base_url' => 'https://example.atlassian.net',
            'email' => 'owner@example.test',
            'api_token' => 'atlassian-token',
            'is_active' => true,
        ]);

        $updateResponse = $this->actingAs($user)->putJson('/api/projects/'.$project->id.'/confluence/spaces', [
            'connection_id' => $connection->id,
            'spaces' => [
                ['id' => '1001', 'key' => 'ENG', 'name' => 'Engineering', 'type' => 'global'],
                ['id' => '1002', 'key' => 'OPS', 'name' => 'Operations', 'type' => 'global'],
            ],
        ]);

        $updateResponse->assertOk();
        $updateResponse->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('project_confluence_spaces', [
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'space_key' => 'ENG',
            'is_enabled' => true,
        ]);

        $syncResponse = $this->actingAs($user)->postJson('/api/projects/'.$project->id.'/confluence/sync');

        $syncResponse->assertAccepted();
        $syncResponse->assertJsonPath('data.spaces_queued', 2);

        Queue::assertPushed(SyncProjectConfluenceSpaceJob::class, 2);
    }

    public function test_sync_job_fetches_confluence_page_and_indexes_it(): void
    {
        Queue::fake();
        Storage::fake('local');

        [$user, $tenant] = $this->createTenantContext();
        $project = $this->createProject($tenant, $user);

        $connection = AtlassianConnection::query()->create([
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
            'base_url' => 'https://example.atlassian.net',
            'email' => 'owner@example.test',
            'api_token' => 'atlassian-token',
            'is_active' => true,
        ]);

        $space = ProjectConfluenceSpace::query()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'atlassian_connection_id' => $connection->id,
            'selected_by' => $user->id,
            'space_id' => '1001',
            'space_key' => 'ENG',
            'space_name' => 'Engineering',
            'space_type' => 'global',
            'is_enabled' => true,
        ]);

        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, '/wiki/rest/api/content?')) {
                return Http::response([
                    'results' => [
                        [
                            'id' => '12345',
                            'title' => 'Release Runbook',
                            '_links' => ['webui' => '/spaces/ENG/pages/12345/Release-Runbook'],
                            'version' => ['when' => '2026-06-01T09:00:00.000Z'],
                        ],
                    ],
                    '_links' => [],
                ], 200);
            }

            if (str_contains($url, '/wiki/rest/api/content/12345?')) {
                return Http::response([
                    'id' => '12345',
                    'title' => 'Release Runbook',
                    '_links' => ['webui' => '/spaces/ENG/pages/12345/Release-Runbook'],
                    'version' => ['when' => '2026-06-01T09:00:00.000Z'],
                    'body' => [
                        'storage' => [
                            'value' => '<h1>Release Runbook</h1><p>Deploy app and run health checks.</p>',
                        ],
                    ],
                ], 200);
            }

            return Http::response([], 404);
        });

        $chunker = Mockery::mock(DocumentChunkingService::class);
        $chunker->shouldReceive('chunk')->once()->andReturn([
            [
                'chunk_index' => 0,
                'content' => 'Release Runbook Deploy app and run health checks.',
                'page_from' => 1,
                'page_to' => 1,
                'metadata' => ['strategy' => 'test'],
            ],
        ]);

        (new SyncProjectConfluenceSpaceJob($space->id))->handle(new ConfluenceApiService(), $chunker);

        $this->assertDatabaseHas('project_documents', [
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'ingestion_type' => 'confluence',
            'source_url' => 'https://example.atlassian.net/wiki/spaces/ENG/pages/12345/Release-Runbook',
            'status' => 'indexed',
        ]);

        $this->assertDatabaseHas('document_chunks', [
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'chunk_index' => 0,
            'content' => 'Release Runbook Deploy app and run health checks.',
        ]);

        Queue::assertPushed(EmbedDocumentChunkJob::class, 1);

        Storage::disk('local')->assertExists('rag/confluence');
    }

    public function test_confluence_service_supports_base_url_with_wiki_suffix(): void
    {
        Http::fake([
            'https://example.atlassian.net/wiki/rest/api/content*' => Http::response([
                'results' => [
                    [
                        'id' => '12345',
                        'title' => 'Release Runbook',
                        '_links' => ['webui' => '/spaces/ENG/pages/12345/Release-Runbook'],
                        'version' => ['when' => '2026-06-01T09:00:00.000Z'],
                    ],
                ],
                '_links' => [],
            ], 200),
        ]);

        $connection = new AtlassianConnection([
            'base_url' => 'https://example.atlassian.net/wiki',
            'email' => 'owner@example.test',
            'api_token' => 'atlassian-token',
            'is_active' => true,
        ]);

        $pages = (new ConfluenceApiService())->listPagesForSpace($connection, 'ENG');

        $this->assertCount(1, $pages);
        $this->assertSame('12345', $pages[0]['id']);

        Http::assertSent(static function ($request): bool {
            return $request->url() === 'https://example.atlassian.net/wiki/rest/api/content?spaceKey=ENG&type=page&limit=50&expand=version';
        });
    }

    public function test_confluence_service_error_includes_status_and_url(): void
    {
        Http::fake([
            '*' => Http::response(['message' => 'Forbidden'], 403),
        ]);

        $connection = new AtlassianConnection([
            'base_url' => 'https://example.atlassian.net',
            'email' => 'owner@example.test',
            'api_token' => 'atlassian-token',
            'is_active' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to fetch Confluence pages. [403');
        $this->expectExceptionMessage('url=https://example.atlassian.net/wiki/rest/api/content?spaceKey=ENG&type=page&limit=50&expand=version');

        (new ConfluenceApiService())->listPagesForSpace($connection, 'ENG');
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function createTenantContext(): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['name' => 'Tenant Alpha']);
        $tenant->users()->attach($user->id, ['role' => 'owner']);

        return [$user, $tenant];
    }

    private function createProject(Tenant $tenant, User $owner): Project
    {
        return Project::query()->create([
            'tenant_id' => $tenant->id,
            'owner_id' => $owner->id,
            'name' => 'Docs Project',
            'slug' => 'docs-project',
            'widget_key' => 'widget-key-'.str_repeat('x', 25),
        ]);
    }
}
