<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_assistant_assistables', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Polymorphe Beziehung
            $table->morphs('assistable');
            
            // AI Assistant Referenz
            $table->foreignId('ai_assistant_id')->constrained('ai_assistant_assistants')->onDelete('cascade');
            
            // Zusätzliche Metadaten
            $table->json('metadata')->nullable();
            
            // Multi-Tenant Support
            $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('cascade');
            $table->foreignId('created_by_user_id')->constrained('users')->onDelete('cascade');
            
            $table->timestamps();
            
            // Indexes (Hinweis: morphs() legt bereits einen zusammengesetzten Index für assistable_* an)
            $table->index(['ai_assistant_id']);
            $table->index(['team_id']);
            $table->index(['created_by_user_id']);
            
            // Unique constraint - genau EIN Assistant pro Model-Instanz
            $table->unique(['assistable_type', 'assistable_id'], 'unique_assistable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_assistables');
    }
};
