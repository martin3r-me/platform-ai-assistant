<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_assistant_assistants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('openai_assistant_id')->unique(); // OpenAI's assistant ID
            $table->string('name', 256); // Max 256 chars
            $table->text('description')->nullable(); // Max 512 chars
            $table->text('instructions')->nullable(); // Max 256,000 chars
            $table->foreignId('ai_model_id')->nullable()->constrained('ai_assistant_models')->onDelete('set null');
            $table->json('tools')->nullable(); // Array of tools
            $table->json('tool_resources')->nullable(); // Tool resources
            $table->json('metadata')->nullable(); // 16 key-value pairs
            $table->json('response_format')->nullable(); // auto or object
            $table->decimal('temperature', 3, 2)->nullable(); // 0-2, default 1
            $table->decimal('top_p', 3, 2)->nullable(); // 0-1, default 1
            $table->enum('reasoning_effort', ['minimal', 'low', 'medium', 'high'])->default('medium');
            $table->boolean('is_active')->default(true);
            
            // Multi-Tenant Support
            $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('cascade');
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade'); // Privater Besitzer
            
            // Ownership Type: 'team' oder 'user'
            $table->enum('ownership_type', ['team', 'user'])->default('team');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['is_active']);
            $table->index(['team_id']);
            $table->index(['created_by_user_id']);
            $table->index(['user_id']);
            $table->index(['ownership_type']);
            
            // Unique constraint: Entweder team_id ODER user_id muss gesetzt sein
            $table->unique(['team_id', 'user_id'], 'ai_assistant_assistant_ownership_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_assistants');
    }
};
