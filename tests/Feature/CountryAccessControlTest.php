<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CountryAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_user_with_multiple_countries(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson('/api/admin/users', [
            'name' => 'Germany France Manager',
            'email' => 'manager@example.com',
            'password' => 'secret-password',
            'role' => 'manager',
            'status' => 'active',
            'country_codes' => ['de', 'fr'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.role', 'manager')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.country_codes.0', 'DE')
            ->assertJsonPath('data.country_codes.1', 'FR');
    }

    public function test_country_manager_sees_only_assigned_country_tenants_and_projects(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $manager->countries()->sync(['DE']);

        $germanTenant = Tenant::query()->create(['name' => 'Germany Tenant', 'country_code' => 'DE']);
        $frenchTenant = Tenant::query()->create(['name' => 'France Tenant', 'country_code' => 'FR']);

        Project::query()->create([
            'tenant_id' => $germanTenant->id,
            'country_code' => 'DE',
            'owner_id' => $manager->id,
            'name' => 'Germany Project',
            'slug' => 'germany-project',
            'widget_key' => 'wk-'.str_repeat('d', 30),
        ]);
        Project::query()->create([
            'tenant_id' => $frenchTenant->id,
            'country_code' => 'FR',
            'owner_id' => $manager->id,
            'name' => 'France Project',
            'slug' => 'france-project',
            'widget_key' => 'wk-'.str_repeat('f', 30),
        ]);

        $this->actingAs($manager)
            ->getJson('/api/tenants')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Germany Tenant');

        $this->actingAs($manager)
            ->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Germany Project');
    }

    public function test_country_manager_cannot_create_tenant_outside_assigned_country(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $manager->countries()->sync(['DE']);

        $this->actingAs($manager)
            ->postJson('/api/tenants', [
                'name' => 'France Tenant',
                'country_code' => 'FR',
            ])
            ->assertForbidden();
    }

    public function test_country_dropdown_only_returns_allowed_countries(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $manager->countries()->sync(['DE', 'FR']);
        Country::query()->where('code', 'FR')->update(['status' => 'inactive']);

        $this->actingAs($manager)
            ->getJson('/api/countries')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'DE');
    }

    public function test_admin_can_delete_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'manager']);

        $this->actingAs($admin)
            ->deleteJson("/api/admin/users/{$user->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
