<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_assistant_models', function (Blueprint $table) {
            $table->id(); // Auto-increment primary key
            $table->uuid('uuid')->unique();
            $table->string('model_id', 255)->unique(); // OpenAI's model ID (e.g., gpt-4o, gpt-4o-mini)
            $table->string('name'); // Display name
            $table->text('description')->nullable();
            $table->string('category')->nullable(); // e.g., 'gpt-4', 'gpt-3.5', 'embedding'
            $table->boolean('is_active')->default(true);
            $table->boolean('is_deprecated')->default(false);
            $table->timestamp('deprecated_at')->nullable();
            $table->json('api_metadata')->nullable(); // Full response from OpenAI API
            $table->timestamp('last_api_check')->nullable();
            
            // Multi-Tenant Support (nullable für globale Models)
            $table->foreignId('team_id')->nullable()->constrained('teams')->onDelete('cascade');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->onDelete('cascade');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['is_active', 'is_deprecated']);
            $table->index(['category', 'is_active']);
            $table->index(['team_id']);
            $table->index(['created_by_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_models');
    }
};
