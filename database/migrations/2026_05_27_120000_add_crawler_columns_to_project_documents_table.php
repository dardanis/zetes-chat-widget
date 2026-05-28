<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_documents', function (Blueprint $table): void {
            $table->string('ingestion_type')->default('pdf')->after('status');
            $table->string('source_url')->nullable()->after('ingestion_type');

            $table->index(['project_id', 'ingestion_type']);
            $table->index(['project_id', 'source_url']);
        });
    }

    public function down(): void
    {
        Schema::table('project_documents', function (Blueprint $table): void {
            $table->dropIndex(['project_id', 'ingestion_type']);
            $table->dropIndex(['project_id', 'source_url']);

            $table->dropColumn(['ingestion_type', 'source_url']);
        });
    }
};

