<?php

namespace Platform\AiAssistant\Livewire\Assistant;

use Livewire\Component;
use Livewire\WithPagination;
use Platform\AiAssistant\Models\AiAssistantAssistant;
use Platform\AiAssistant\Services\OpenAIModelService;
use Platform\AiAssistant\Services\AssistantService;

class Index extends Component
{
    use WithPagination;

    // Modal State
    public $modalShow = false;
    
    // Search & Filter
    public $search = '';
    public $filter = 'all'; // all, team, user
    public $sortBy = 'created_at';
    public $sortDirection = 'desc';
    
    // Form Data
    public $name = '';
    public $description = '';
    public $instructions = '';
    public $model = 'gpt-5-mini';
    public $tools = [];
    public $tool_resources = [];
    public $metadata = [];
    public $response_format = 'auto';
    public $temperature = 1.0;
    public $top_p = 1.0;
    public $reasoning_effort = 'medium';
    public $ownership_type = 'team';

    protected $queryString = [
        'search' => ['except' => ''],
        'filter' => ['except' => 'all'],
        'sortBy' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    protected $rules = [
        'name' => 'required|string|max:256',
        'description' => 'nullable|string|max:512',
        'instructions' => 'required|string|max:256000',
        'model' => 'required|string|exists:ai_assistant_models,model_id',
        'tools' => 'array',
        'tool_resources' => 'array',
        'metadata' => 'array',
        'response_format' => 'nullable|string|in:auto,json_object',
        'temperature' => 'nullable|numeric|between:0,2',
        'top_p' => 'nullable|numeric|between:0,1',
        'reasoning_effort' => 'nullable|string|in:minimal,low,medium,high',
        'ownership_type' => 'required|in:team,user',
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function sortBy($field)
    {
        if ($this->sortBy === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function createAssistant()
    {
        $this->validate();
        
        // Finde das AI Model basierend auf der model_id
        $aiModel = \Platform\AiAssistant\Models\AiAssistantModel::where('model_id', $this->model)
            ->where('is_active', true)
            ->where('is_deprecated', false)
            ->first();
            
        if (!$aiModel) {
            $this->dispatch('notifications:store', [
                'title' => 'Model nicht verfügbar',
                'message' => 'Das ausgewählte Model ist nicht verfügbar.',
                'notice_type' => 'error',
            ]);
            return;
        }
        
        try {
            // Erstelle Assistant bei OpenAI
            $assistantService = app(AssistantService::class);
            $openaiAssistantId = $assistantService->createAssistant([
                'name' => $this->name,
                'description' => $this->description,
                'instructions' => $this->instructions,
                'ai_model_id' => $aiModel->id,
                'tools' => $this->tools,
                'tool_resources' => $this->tool_resources,
                'metadata' => $this->metadata,
                'response_format' => $this->response_format,
                'temperature' => $this->temperature,
                'top_p' => $this->top_p,
                'reasoning_effort' => $this->reasoning_effort,
            ]);
            
            // Erstelle Assistant in der Datenbank
            $assistant = AiAssistantAssistant::create([
                'openai_assistant_id' => $openaiAssistantId,
                'name' => $this->name,
                'description' => $this->description,
                'instructions' => $this->instructions,
                'ai_model_id' => $aiModel->id,
                'tools' => $this->tools,
                'tool_resources' => $this->tool_resources,
                'metadata' => $this->metadata,
                'response_format' => $this->response_format,
                'temperature' => $this->temperature,
                'top_p' => $this->top_p,
                'reasoning_effort' => $this->reasoning_effort,
                'ownership_type' => $this->ownership_type,
                'team_id' => auth()->user()->current_team_id,
                'created_by_user_id' => auth()->id(),
                'user_id' => $this->ownership_type === 'user' ? auth()->id() : null,
            ]);

            $this->resetForm();
            $this->modalShow = false;
            
            $this->dispatch('notifications:store', [
                'title' => 'Assistant erstellt',
                'message' => 'Der AI Assistant wurde erfolgreich bei OpenAI erstellt.',
                'notice_type' => 'success',
                'noticable_type' => AiAssistantAssistant::class,
                'noticable_id' => $assistant->id,
            ]);
            
        } catch (\Exception $e) {
            // Für Error-Notifications ohne spezifische Referenz verwenden wir eine andere Strategie
            $this->dispatch('notifications:store', [
                'title' => 'Fehler beim Erstellen',
                'message' => 'Der Assistant konnte nicht erstellt werden: ' . $e->getMessage(),
                'notice_type' => 'error',
                'noticable_type' => \Platform\Core\Models\User::class,
                'noticable_id' => auth()->id(),
            ]);
        }
    }

    public function resetForm()
    {
        $this->reset([
            'name', 'description', 'instructions', 'model', 'tools', 'tool_resources', 
            'metadata', 'response_format', 'temperature', 'top_p', 'reasoning_effort', 'ownership_type'
        ]);
        $this->model = 'gpt-5-mini';
        $this->response_format = 'auto';
        $this->temperature = 1.0;
        $this->top_p = 1.0;
        $this->reasoning_effort = 'medium';
        $this->ownership_type = 'team';
    }

    public function openCreateModal()
    {
        $this->modalShow = true;
    }

    public function closeCreateModal()
    {
        $this->modalShow = false;
        $this->resetForm();
    }

    public function render()
    {
        $assistants = AiAssistantAssistant::query()
            ->with('model') // Eager load das Model
            ->accessibleBy(auth()->user())
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('instructions', 'like', '%' . $this->search . '%');
            })
            ->when($this->filter === 'team', function ($query) {
                $query->where('ownership_type', 'team');
            })
            ->when($this->filter === 'user', function ($query) {
                $query->where('ownership_type', 'user');
            })
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(15);

        // Dynamische Modelle von der API
        $modelService = app(OpenAIModelService::class);
        $models = $modelService->getAvailableModels(auth()->user()->current_team_id);

        $ownershipTypes = collect([
            ['id' => 'team', 'name' => 'Team (Geteilt)'],
            ['id' => 'user', 'name' => 'Privat (Nur ich)']
        ]);

        // Prüfe ob das aktuelle Model reasoning_effort unterstützt
        $currentModel = $models->firstWhere('model_id', $this->model);
        $supportsReasoningEffort = $currentModel && $this->isReasoningEffortSupported($currentModel->model_id);

        return view('ai-assistant::livewire.assistant.index', [
            'assistants' => $assistants,
            'models' => $models,
            'ownershipTypes' => $ownershipTypes,
            'supportsReasoningEffort' => $supportsReasoningEffort,
        ])->layout('platform::layouts.app');
    }

    /**
     * Prüft ob reasoning_effort für das Model unterstützt wird
     */
    private function isReasoningEffortSupported(string $modelId): bool
    {
        $supportedModels = [
            'gpt-4o', 'gpt-4o-mini', 'gpt-4o-preview',
            'gpt-4o-2024-07-18', 'gpt-4o-mini-2024-07-18', 'gpt-4o-preview-2024-07-18',
            'gpt-4o-2024-08-01', 'gpt-4o-mini-2024-08-01', 'gpt-4o-preview-2024-08-01',
            'gpt-4o-2024-09-01', 'gpt-4o-mini-2024-09-01', 'gpt-4o-preview-2024-09-01',
            'gpt-4o-2024-10-01', 'gpt-4o-mini-2024-10-01', 'gpt-4o-preview-2024-10-01',
            'gpt-4o-2024-11-01', 'gpt-4o-mini-2024-11-01', 'gpt-4o-preview-2024-11-01',
            'gpt-4o-2024-12-01', 'gpt-4o-mini-2024-12-01', 'gpt-4o-preview-2024-12-01',
            'gpt-4o-2025-01-01', 'gpt-4o-mini-2025-01-01', 'gpt-4o-preview-2025-01-01',
            'gpt-4o-2025-02-01', 'gpt-4o-mini-2025-02-01', 'gpt-4o-preview-2025-02-01',
            'gpt-4o-2025-03-01', 'gpt-4o-mini-2025-03-01', 'gpt-4o-preview-2025-03-01',
            'gpt-4o-2025-04-01', 'gpt-4o-mini-2025-04-01', 'gpt-4o-preview-2025-04-01',
            'gpt-4o-2025-05-01', 'gpt-4o-mini-2025-05-01', 'gpt-4o-preview-2025-05-01',
            'gpt-4o-2025-06-01', 'gpt-4o-mini-2025-06-01', 'gpt-4o-preview-2025-06-01',
            'gpt-4o-2025-07-01', 'gpt-4o-mini-2025-07-01', 'gpt-4o-preview-2025-07-01',
            'gpt-4o-2025-08-01', 'gpt-4o-mini-2025-08-01', 'gpt-4o-preview-2025-08-01',
            'gpt-4o-2025-09-01', 'gpt-4o-mini-2025-09-01', 'gpt-4o-preview-2025-09-01',
            'gpt-4o-2025-10-01', 'gpt-4o-mini-2025-10-01', 'gpt-4o-preview-2025-10-01',
            'gpt-4o-2025-11-01', 'gpt-4o-mini-2025-11-01', 'gpt-4o-preview-2025-11-01',
            'gpt-4o-2025-12-01', 'gpt-4o-mini-2025-12-01', 'gpt-4o-preview-2025-12-01',
        ];

        return in_array($modelId, $supportedModels);
    }
}
