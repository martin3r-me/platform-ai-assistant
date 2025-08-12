<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_assistant_tool_calls', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('openai_tool_call_id')->unique(); // OpenAI's tool call ID
            $table->foreignId('run_id')->constrained('ai_assistant_runs')->onDelete('cascade');
            $table->foreignId('thread_id')->constrained('ai_assistant_threads')->onDelete('cascade');
            
            $table->string('type'); // function, code_interpreter, retrieval
            $table->string('function_name')->nullable(); // For function calls
            $table->json('function_arguments')->nullable(); // Function arguments
            $table->text('output')->nullable(); // Tool call output
            
            $table->string('status')->default('pending'); // pending, completed, failed
            $table->json('metadata')->nullable();
            
            // Multi-Tenant Support
            $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('cascade');
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('cascade');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['run_id']);
            $table->index(['thread_id']);
            $table->index(['type']);
            $table->index(['status']);
            $table->index(['team_id']);
            $table->index(['created_by_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_tool_calls');
    }
};
