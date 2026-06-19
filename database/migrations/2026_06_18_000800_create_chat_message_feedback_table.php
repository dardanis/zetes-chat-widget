<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_message_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chat_message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rating', 20);
            $table->string('channel', 30)->default('dashboard');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'project_id']);
            $table->index(['chat_message_id', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_feedback');
    }
};
