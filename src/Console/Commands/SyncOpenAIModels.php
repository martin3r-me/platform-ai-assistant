<?php

namespace Platform\AiAssistant\Console\Commands;

use Illuminate\Console\Command;
use Platform\AiAssistant\Services\OpenAIModelService;
use Platform\Core\Models\Team;

class SyncOpenAIModels extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai-assistant:sync-models 
                            {--team-id= : Specific team ID to sync models for}
                            {--all-teams : Sync models for all teams}
                            {--dry-run : Show what would be synced without actually doing it}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize OpenAI models with the database';

    /**
     * Execute the console command.
     */
    public function handle(OpenAIModelService $modelService)
    {
        $this->info('🤖 Starting OpenAI Models Synchronization...');

        // Prüfe API Key
        if (!config('ai-assistant.openai.api_key')) {
            $this->error('❌ OpenAI API Key not configured!');
            $this->error('Please set OPENAI_API_KEY in your .env file');
            return 1;
        }

        // Erstelle globale Modelle (ohne team_id)
        $this->info("\n🔄 Syncing global models...");

        try {
            if ($this->option('dry-run')) {
                $this->info("🔍 DRY RUN: Would sync global models");
                return 0;
            }

            $result = $modelService->syncModels(null, null);

            $syncedCount = count($result['synced']);
            $errorCount = count($result['errors']);

            if ($syncedCount > 0) {
                $this->info("✅ Synced {$syncedCount} global models");
            }

            if ($errorCount > 0) {
                $this->warn("⚠️  {$errorCount} errors during sync:");
                foreach ($result['errors'] as $error) {
                    $this->warn("   - {$error}");
                }
            }

            // Zeige synced models
            if ($syncedCount > 0) {
                $this->table(
                    ['Model ID', 'Name', 'Category', 'Status'],
                    collect($result['synced'])->map(function ($model) {
                        return [
                            $model->model_id,
                            $model->name,
                            $model->category,
                            $model->isAvailable() ? '✅ Available' : '❌ Deprecated'
                        ];
                    })->toArray()
                );
            }

            // Summary
            $this->newLine();
            $this->info('📊 Synchronization Summary:');
            $this->info("   ✅ Total models synced: {$syncedCount}");
            $this->info("   ❌ Total errors: {$errorCount}");

            if ($errorCount === 0) {
                $this->info('🎉 All models synchronized successfully!');
            } else {
                $this->warn('⚠️  Some errors occurred during synchronization');
            }

            return $errorCount === 0 ? 0 : 1;

        } catch (\Exception $e) {
            $this->error("❌ Error syncing global models: {$e->getMessage()}");
            return 1;
        }
    }
}
