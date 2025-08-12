<?php

namespace Platform\AiAssistant\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\AiAssistant\Models\AiAssistantAssistant;
use Platform\AiAssistant\Models\AiAssistantThread;
use Platform\AiAssistant\Models\AiAssistantMessage;
use Platform\AiAssistant\Models\AiAssistantRun;
use Platform\AiAssistant\Models\AiAssistantModel;

class Dashboard extends Component
{
    public string $perspective = 'team';

    #[Computed]
    public function totalAssistants(): int
    {
        return $this->getQueryBuilder(AiAssistantAssistant::class)->count();
    }

    #[Computed]
    public function totalThreads(): int
    {
        return $this->getQueryBuilder(AiAssistantThread::class)->count();
    }

    #[Computed]
    public function totalMessages(): int
    {
        return $this->getQueryBuilder(AiAssistantMessage::class)->count();
    }

    #[Computed]
    public function totalRuns(): int
    {
        return $this->getQueryBuilder(AiAssistantRun::class)->count();
    }

    #[Computed]
    public function activeRuns(): int
    {
        return $this->getQueryBuilder(AiAssistantRun::class)
            ->whereIn('status', ['queued', 'in_progress', 'requires_action'])
            ->count();
    }

    #[Computed]
    public function completedRuns(): int
    {
        return $this->getQueryBuilder(AiAssistantRun::class)
            ->where('status', 'completed')
            ->count();
    }

    #[Computed]
    public function failedRuns(): int
    {
        return $this->getQueryBuilder(AiAssistantRun::class)
            ->where('status', 'failed')
            ->count();
    }

    #[Computed]
    public function activeThreads(): int
    {
        return $this->getQueryBuilder(AiAssistantThread::class)
            ->where('status', 'active')
            ->count();
    }

    #[Computed]
    public function assistantsWithoutModel(): int
    {
        return $this->getQueryBuilder(AiAssistantAssistant::class)
            ->whereNull('ai_model_id')
            ->count();
    }

    #[Computed]
    public function threadsWithoutAssistant(): int
    {
        return $this->getQueryBuilder(AiAssistantThread::class)
            ->whereNull('assistant_id')
            ->count();
    }

    #[Computed]
    public function archivedThreads(): int
    {
        return $this->getQueryBuilder(AiAssistantThread::class)
            ->where('status', 'archived')
            ->count();
    }

    #[Computed]
    public function teamAssistants(): int
    {
        return $this->getQueryBuilder(AiAssistantAssistant::class)
            ->where('ownership_type', 'team')
            ->count();
    }

    #[Computed]
    public function recentAssistants()
    {
        return $this->getQueryBuilder(AiAssistantAssistant::class)
            ->with(['model'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function recentThreads()
    {
        return $this->getQueryBuilder(AiAssistantThread::class)
            ->with(['assistant'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
    }

    #[Computed]
    public function topAiModels()
    {
        return $this->getQueryBuilder(AiAssistantAssistant::class)
            ->selectRaw('ai_model_id, COUNT(*) as count')
            ->whereNotNull('ai_model_id')
            ->groupBy('ai_model_id')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $model = AiAssistantModel::find($item->ai_model_id);
                return (object) [
                    'name' => $model ? $model->name : 'Unbekanntes Model',
                    'count' => $item->count
                ];
            });
    }

    private function getQueryBuilder($modelClass)
    {
        $query = $modelClass::query();

        if ($this->perspective === 'personal') {
            // Für Messages und Runs verwenden wir created_by_user_id
            if (in_array($modelClass, [AiAssistantMessage::class, AiAssistantRun::class])) {
                $query->where('created_by_user_id', auth()->id());
            } else {
                $query->where('user_id', auth()->id());
            }
        } else {
            $query->where('team_id', auth()->user()->current_team_id);
        }

        return $query;
    }

    public function render()
    {
        return view('ai-assistant::livewire.dashboard')
            ->layout('platform::layouts.app');
    }
}
