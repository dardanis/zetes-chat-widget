<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_chunks', function (Blueprint $table): void {
            $table->index(['tenant_id', 'project_id', 'project_document_id'], 'document_chunks_scope_document_idx');
            $table->index(['tenant_id', 'project_id', 'chunk_index'], 'document_chunks_scope_chunk_idx');
        });

        Schema::table('project_documents', function (Blueprint $table): void {
            $table->index(['tenant_id', 'project_id', 'ingestion_type'], 'project_documents_scope_ingestion_idx');
            $table->index(['tenant_id', 'project_id', 'status'], 'project_documents_scope_status_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("CREATE INDEX IF NOT EXISTS document_chunks_content_fts_idx ON document_chunks USING gin (to_tsvector('simple', content))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS document_chunks_content_fts_idx');
        }

        Schema::table('project_documents', function (Blueprint $table): void {
            $table->dropIndex('project_documents_scope_ingestion_idx');
            $table->dropIndex('project_documents_scope_status_idx');
        });

        Schema::table('document_chunks', function (Blueprint $table): void {
            $table->dropIndex('document_chunks_scope_document_idx');
            $table->dropIndex('document_chunks_scope_chunk_idx');
        });
    }
};

