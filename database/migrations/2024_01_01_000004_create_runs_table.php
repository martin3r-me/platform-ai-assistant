<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_assistant_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('openai_run_id')->unique(); // OpenAI's run ID
            $table->foreignId('thread_id')->constrained('ai_assistant_threads')->onDelete('cascade');
            $table->foreignId('assistant_id')->constrained('ai_assistant_assistants')->onDelete('cascade');
            
            $table->string('status'); // queued, in_progress, requires_action, cancelling, cancelled, failed, completed, expired
            $table->string('type')->default('message_creation'); // message_creation, tool_calls
            
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            
            $table->json('required_action')->nullable(); // Tool calls required
            $table->json('last_error')->nullable(); // Error details
            $table->json('metadata')->nullable();
            
            // Multi-Tenant Support
            $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('cascade');
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('cascade');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['thread_id']);
            $table->index(['assistant_id']);
            $table->index(['status']);
            $table->index(['team_id']);
            $table->index(['created_by_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_runs');
    }
};
