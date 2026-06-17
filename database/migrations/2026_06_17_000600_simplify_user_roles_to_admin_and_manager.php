<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereIn('role', ['country_manager', 'project_user'])
            ->update(['role' => 'manager']);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('role', 'manager')
            ->update(['role' => 'country_manager']);
    }
};
