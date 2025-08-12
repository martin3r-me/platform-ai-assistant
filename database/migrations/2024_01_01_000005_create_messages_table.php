<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('openai_message_id')->unique(); // OpenAI's message ID
            $table->foreignId('thread_id')->constrained('ai_assistant_threads')->onDelete('cascade');
            $table->foreignId('run_id')->nullable()->constrained('ai_assistant_runs')->onDelete('set null');
            
            $table->enum('role', ['user', 'assistant']); // Message role
            $table->text('content'); // Message content
            $table->json('file_ids')->nullable(); // Attached file IDs
            $table->json('metadata')->nullable();
            
            // Multi-Tenant Support
            $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('cascade');
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('cascade');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['thread_id']);
            $table->index(['run_id']);
            $table->index(['role']);
            $table->index(['team_id']);
            $table->index(['created_by_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_messages');
    }
};
