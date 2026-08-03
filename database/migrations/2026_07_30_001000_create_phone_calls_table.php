<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_calls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('call_sid')->unique();
            $table->string('from_number');
            $table->string('to_number');
            $table->string('from_country', 2)->nullable();
            $table->string('from_city')->nullable();
            $table->string('status')->default('ringing');
            $table->string('direction')->default('inbound');
            $table->unsignedInteger('turn_count')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('recording_url')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'project_id']);
            // Powers the per-project caller directory.
            $table->index(['project_id', 'from_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_calls');
    }
};
