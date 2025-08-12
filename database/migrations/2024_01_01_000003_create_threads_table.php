<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_assistant_threads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('openai_thread_id')->unique(); // OpenAI's thread ID
            $table->string('title', 255); // Thread-Titel
            $table->foreignId('assistant_id')->constrained('ai_assistant_assistants')->onDelete('cascade');
            $table->foreignId('ai_model_id')->nullable()->constrained('ai_assistant_models')->onDelete('set null'); // Aktuelles Model für diesen Thread
            
            // Polymorphic Context (für alle Module) - Optional
            $table->string('context_type')->nullable(); // Optional: z.B. 'App\Models\Project'
            $table->unsignedBigInteger('context_id')->nullable(); // Optional: ID des Kontext-Objekts
            $table->json('context_metadata')->nullable(); // Zusätzliche Kontext-Daten
            
            $table->string('status')->default('active');
            $table->json('metadata')->nullable();
            
            // Multi-Tenant Support
            $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('cascade');
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade'); // Privater Besitzer
            
            // Ownership Type: 'team' oder 'user'
            $table->enum('ownership_type', ['team', 'user'])->default('team');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['status']);
            $table->index(['team_id']);
            $table->index(['created_by_user_id']);
            $table->index(['user_id']);
            $table->index(['ownership_type']);
            $table->index(['context_type', 'context_id']); // Polymorphic index
            
            // Unique constraint: Entweder team_id ODER user_id muss gesetzt sein
            $table->unique(['team_id', 'user_id'], 'ai_assistant_thread_ownership_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_threads');
    }
};
