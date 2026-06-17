<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->string('code', 2)->primary();
            $table->string('name');
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        $now = now();
        DB::table('countries')->insert(
            collect(config('countries'))
                ->map(fn (string $name, string $code): array => [
                    'code' => $code,
                    'name' => $name,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->values()
                ->all()
        );

        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('manager')->after('password')->index();
            $table->string('status')->default('active')->after('role')->index();
        });

        $firstUserId = DB::table('users')->orderBy('id')->value('id');

        if ($firstUserId !== null) {
            DB::table('users')->where('id', $firstUserId)->update(['role' => 'admin', 'status' => 'active']);
        }

        Schema::create('user_countries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('country_code', 2);
            $table->timestamps();

            $table->foreign('country_code')->references('code')->on('countries')->cascadeOnDelete();
            $table->unique(['user_id', 'country_code']);
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('country_code', 2)->nullable()->after('name')->index();
            $table->string('status')->default('active')->after('country_code')->index();
            $table->foreign('country_code')->references('code')->on('countries')->nullOnDelete();
        });

        Schema::table('projects', function (Blueprint $table): void {
            $table->string('country_code', 2)->nullable()->after('tenant_id')->index();
            $table->string('status')->default('active')->after('country_code')->index();
            $table->foreign('country_code')->references('code')->on('countries')->nullOnDelete();
        });

        Schema::create('project_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('manager');
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_user');

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropForeign(['country_code']);
            $table->dropColumn(['country_code', 'status']);
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropForeign(['country_code']);
            $table->dropColumn(['country_code', 'status']);
        });

        Schema::dropIfExists('user_countries');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['role', 'status']);
        });

        Schema::dropIfExists('countries');
    }
};
