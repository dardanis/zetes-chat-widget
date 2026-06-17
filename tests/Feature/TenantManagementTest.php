<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_a_tenant(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/tenants', ['name' => 'Acme Corp', 'country_code' => 'DE']);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Acme Corp');

        $this->assertDatabaseHas('tenants', ['name' => 'Acme Corp', 'country_code' => 'DE']);

        // Creator is attached as owner
        $tenant = Tenant::query()->where('name', 'Acme Corp')->first();
        $this->assertTrue($tenant->users()->whereKey($user->id)->exists());
        $this->assertSame('owner', $tenant->users()->whereKey($user->id)->first()->pivot->role);
    }

    public function test_authenticated_user_can_list_their_tenants(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        $user->countries()->sync(['DE']);
        $tenant = Tenant::query()->create(['name' => 'My Tenant', 'country_code' => 'DE']);
        $tenant->users()->attach($user->id, ['role' => 'owner']);

        // Another tenant the user is NOT a member of
        Tenant::query()->create(['name' => 'Other Tenant', 'country_code' => 'FR']);

        $response = $this->actingAs($user)
            ->getJson('/api/tenants');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'My Tenant');
    }

    public function test_create_tenant_requires_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/tenants', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_unauthenticated_user_cannot_list_tenants(): void
    {
        $this->getJson('/api/tenants')
            ->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_create_tenant(): void
    {
        $this->postJson('/api/tenants', ['name' => 'Forbidden'])
            ->assertUnauthorized();
    }

    public function test_user_can_update_their_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['name' => 'Old Name', 'country_code' => 'DE']);
        $tenant->users()->attach($user->id, ['role' => 'owner']);

        $this->actingAs($user)
            ->putJson("/api/tenants/{$tenant->id}", ['name' => 'New Name', 'country_code' => 'DE'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'name' => 'New Name']);
    }

    public function test_user_cannot_update_foreign_tenant(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        $user->countries()->sync(['DE']);
        $tenant = Tenant::query()->create(['name' => 'Foreign', 'country_code' => 'FR']);

        $this->actingAs($user)
            ->putJson("/api/tenants/{$tenant->id}", ['name' => 'Hijacked'])
            ->assertForbidden();
    }

    public function test_user_can_delete_their_tenant(): void
    {
        $user = User::factory()->create();
        $tenant = Tenant::query()->create(['name' => 'To Delete', 'country_code' => 'DE']);
        $tenant->users()->attach($user->id, ['role' => 'owner']);

        $this->actingAs($user)
            ->deleteJson("/api/tenants/{$tenant->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
    }

    public function test_user_cannot_delete_foreign_tenant(): void
    {
        $user = User::factory()->create(['role' => 'manager']);
        $user->countries()->sync(['DE']);
        $tenant = Tenant::query()->create(['name' => 'Not Mine', 'country_code' => 'FR']);

        $this->actingAs($user)
            ->deleteJson("/api/tenants/{$tenant->id}")
            ->assertForbidden();
    }
}
