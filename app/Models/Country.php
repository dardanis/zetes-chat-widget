<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['code', 'name', 'status'];

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class, 'country_code', 'code');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'country_code', 'code');
    }
}
