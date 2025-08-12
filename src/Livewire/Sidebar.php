<?php

namespace Platform\AiAssistant\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Schema;
use Platform\AiAssistant\Models\AiAssistantAssistant;
use Platform\AiAssistant\Models\AiAssistantThread;

class Sidebar extends Component
{
    public $assistantsCount = 0;
    public $activeThreadsCount = 0;
    public $recentThreads = [];

    public function mount()
    {
        $this->loadCounts();
        $this->loadRecentThreads();
    }

    public function loadCounts()
    {
        try {
            $user = auth()->user();
            
            // Prüfen ob Tabellen existieren
            if (Schema::hasTable('ai_assistant_assistants') && Schema::hasTable('ai_assistant_threads')) {
                $this->assistantsCount = AiAssistantAssistant::accessibleBy($user)->count();
                $this->activeThreadsCount = AiAssistantThread::accessibleBy($user)
                    ->where('status', 'active')
                    ->count();
            }
        } catch (\Exception $e) {
            // Fallback: Keine Daten verfügbar
            $this->assistantsCount = 0;
            $this->activeThreadsCount = 0;
        }
    }

    public function loadRecentThreads()
    {
        try {
            $user = auth()->user();
            
            // Prüfen ob Tabellen existieren
            if (Schema::hasTable('ai_assistant_threads')) {
                $this->recentThreads = AiAssistantThread::accessibleBy($user)
                    ->with(['assistant', 'context'])
                    ->orderBy('updated_at', 'desc')
                    ->limit(5)
                    ->get();
            }
        } catch (\Exception $e) {
            // Fallback: Keine Daten verfügbar
            $this->recentThreads = [];
        }
    }

    public function render()
    {
        return view('ai-assistant::livewire.sidebar')->layout('platform::layouts.app');
    }
}
