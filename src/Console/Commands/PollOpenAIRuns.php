<?php

namespace Platform\AiAssistant\Console\Commands;

use Illuminate\Console\Command;
use Platform\AiAssistant\Models\AiAssistantRun;
use Platform\AiAssistant\Services\RunService;

class PollOpenAIRuns extends Command
{
    protected $signature = 'ai-assistant:poll-runs {--dry-run : Show what would be processed without actually doing it}';
    protected $description = 'Poll OpenAI runs and update their status';

    public function handle(RunService $runService)
    {
        $this->info('🤖 Starting OpenAI Runs Polling (Endless Loop - Ctrl+C to stop)...');

        $iteration = 0;
        
        while (true) {
            $iteration++;
            $this->info("\n🔄 Iteration {$iteration} - " . now()->format('H:i:s'));
            
            // Alle aktiven Runs holen
            $activeRuns = AiAssistantRun::whereIn('status', [
                'queued', 
                'in_progress', 
                'requires_action'
            ])->with(['thread', 'assistant'])->get();

            if ($activeRuns->isEmpty()) {
                $this->info('✅ No active runs found.');
            } else {
                $this->info("🔄 Found {$activeRuns->count()} active runs to check...");

                $processed = 0;
                $completed = 0;
                $errors = 0;

                foreach ($activeRuns as $run) {
                    try {
                        if ($this->option('dry-run')) {
                            $this->line("🔍 DRY RUN: Would check run {$run->openai_run_id} (Status: {$run->status})");
                            continue;
                        }

                        $this->line("🔄 Checking run {$run->openai_run_id} (Thread: {$run->thread->openai_thread_id})...");
                        
                        // Run Status bei OpenAI abfragen
                        $newStatus = $runService->getRunStatus($run->openai_run_id, $run->thread->openai_thread_id);
                        
                        if ($newStatus === null) {
                            $this->warn("  ⚠️  Could not fetch status from OpenAI");
                            continue;
                        }
                        
                        if ($newStatus !== $run->status) {
                            $run->update(['status' => $newStatus]);
                            $this->line("  📝 Status updated: {$run->status} → {$newStatus}");
                            
                            // Wenn Run abgeschlossen, Messages holen
                            if ($newStatus === 'completed') {
                                $this->line("  ✅ Run completed, fetching messages...");
                                $runService->processCompletedRun($run);
                                $completed++;
                            }
                        } else {
                            $this->line("  ⏳ Status unchanged: {$run->status}");
                        }
                        
                        $processed++;
                        
                    } catch (\Exception $e) {
                        $this->error("  ❌ Error processing run {$run->openai_run_id}: " . $e->getMessage());
                        $errors++;
                    }
                }

                $this->info('📊 Iteration Summary:');
                $this->info("   ✅ Runs processed: {$processed}");
                $this->info("   🎉 Runs completed: {$completed}");
                $this->info("   ❌ Errors: {$errors}");
            }
            
            // 5 Sekunden warten bis zur nächsten Iteration
            $this->info('⏳ Waiting 5 seconds before next iteration...');
            sleep(5);
        }
    }
}
