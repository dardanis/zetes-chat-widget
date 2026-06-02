<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atlassian_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('base_url');
            $table->string('email');
            $table->text('api_token');
            $table->string('cloud_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('project_confluence_spaces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('atlassian_connection_id')->constrained('atlassian_connections')->cascadeOnDelete();
            $table->foreignId('selected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('space_id')->nullable();
            $table->string('space_key');
            $table->string('space_name');
            $table->string('space_type')->default('global');
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'atlassian_connection_id', 'space_key'], 'project_confluence_spaces_unique');
            $table->index(['tenant_id', 'project_id', 'is_enabled'], 'project_confluence_spaces_tenant_project_enabled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_confluence_spaces');
        Schema::dropIfExists('atlassian_connections');
    }
};

