<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccessControlService
{
    public function isAdmin(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function isActive(User $user): bool
    {
        return $user->status === 'active';
    }

    public function can(User $user, string $permission): bool
    {
        if (! $this->isActive($user)) {
            return false;
        }

        $permissions = config("permissions.roles.{$user->role}.permissions", []);

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    /**
     * @return Collection<int, string>
     */
    public function countryCodes(User $user): Collection
    {
        if ($this->isAdmin($user)) {
            return DB::table('countries')
                ->where('status', 'active')
                ->orderBy('name')
                ->pluck('code')
                ->values();
        }

        return DB::table('user_countries')
            ->join('countries', 'countries.code', '=', 'user_countries.country_code')
            ->where('user_countries.user_id', $user->id)
            ->where('countries.status', 'active')
            ->orderBy('countries.name')
            ->pluck('user_countries.country_code')
            ->values();
    }

    public function canAccessCountry(User $user, ?string $countryCode): bool
    {
        if (! $this->isActive($user) || $countryCode === null) {
            return false;
        }

        return $this->isAdmin($user) || $this->countryCodes($user)->contains(strtoupper($countryCode));
    }

    public function canAccessTenant(User $user, Tenant $tenant, string $permission = 'tenants.view'): bool
    {
        if (! $this->can($user, $permission)) {
            return false;
        }

        return $this->canAccessCountry($user, $tenant->country_code)
            || $tenant->users()->whereKey($user->id)->exists();
    }

    public function canAccessProject(User $user, Project $project, string $permission = 'projects.view'): bool
    {
        if (! $this->can($user, $permission)) {
            return false;
        }

        return $this->canAccessCountry($user, $project->country_code)
            || $project->users()->whereKey($user->id)->exists()
            || $project->tenant?->users()->whereKey($user->id)->exists();
    }

    public function scopeTenantsFor(User $user, Builder $query): Builder
    {
        if ($this->isAdmin($user)) {
            return $query;
        }

        $countryCodes = $this->countryCodes($user);

        return $query->where(function (Builder $query) use ($countryCodes, $user): void {
            $query->whereIn('country_code', $countryCodes)
                ->orWhereHas('users', fn (Builder $query): Builder => $query->whereKey($user->id));
        });
    }

    public function scopeProjectsFor(User $user, Builder $query): Builder
    {
        if ($this->isAdmin($user)) {
            return $query;
        }

        $countryCodes = $this->countryCodes($user);

        return $query->where(function (Builder $query) use ($countryCodes, $user): void {
            $query->whereIn('country_code', $countryCodes)
                ->orWhereHas('users', fn (Builder $query): Builder => $query->whereKey($user->id))
                ->orWhereHas('tenant.users', fn (Builder $query): Builder => $query->whereKey($user->id));
        });
    }
}
