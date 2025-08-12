<?php

namespace Platform\AiAssistant\Livewire\Assistant;

use Livewire\Component;
use Platform\AiAssistant\Models\AiAssistantAssistant;
use Platform\AiAssistant\Services\OpenAIModelService;

class Show extends Component
{
    public AiAssistantAssistant $assistant;
    public $isDirty = false;

    protected $rules = [
        'assistant.name' => 'required|string|max:256',
        'assistant.description' => 'nullable|string|max:512',
        'assistant.instructions' => 'required|string|max:256000',
        'assistant.ai_model_id' => 'required|exists:ai_assistant_models,id',
        'assistant.tools' => 'array',
        'assistant.tool_resources' => 'array',
        'assistant.metadata' => 'array',
        'assistant.response_format' => 'nullable|string|in:auto,json_object',
        'assistant.temperature' => 'nullable|numeric|between:0,2',
        'assistant.top_p' => 'nullable|numeric|between:0,1',
        'assistant.reasoning_effort' => 'nullable|string|in:minimal,low,medium,high',
        'assistant.ownership_type' => 'required|in:team,user',
        'assistant.is_active' => 'boolean',
    ];

    public function mount(AiAssistantAssistant $assistant)
    {
        $this->assistant = $assistant;
        
        // Check if user has access to this assistant
        if (!$this->assistant->isAccessibleBy(auth()->user())) {
            abort(403);
        }
    }

    public function updated($propertyName)
    {
        $this->isDirty = true;
    }

    public function save()
    {
        try {
            // Update bei OpenAI
            $assistantService = app(\Platform\AiAssistant\Services\AssistantService::class);
            $aiModel = $this->assistant->model;
            
            $updateData = [
                'name' => $this->assistant->name,
                'description' => $this->assistant->description,
                'instructions' => $this->assistant->instructions,
                'ai_model_id' => $this->assistant->ai_model_id,
                'tools' => $this->assistant->tools,
                'tool_resources' => $this->assistant->tool_resources,
                'metadata' => $this->assistant->metadata,
                'response_format' => $this->assistant->response_format,
                'temperature' => $this->assistant->temperature,
                'top_p' => $this->assistant->top_p,
                'reasoning_effort' => $this->assistant->reasoning_effort,
            ];
            
            $success = $assistantService->updateAssistant($this->assistant->openai_assistant_id, $updateData);
            
            if ($success) {
                $this->assistant->save();
                $this->isDirty = false;
                
                $this->dispatch('notifications:store', [
                    'title' => 'Assistant gespeichert',
                    'message' => 'Die Änderungen wurden erfolgreich bei OpenAI und in der Datenbank gespeichert.',
                    'notice_type' => 'success',
                    'noticable_type' => AiAssistantAssistant::class,
                    'noticable_id' => $this->assistant->id,
                ]);
            } else {
                $this->dispatch('notifications:store', [
                    'title' => 'Fehler beim Speichern',
                    'message' => 'Die Änderungen konnten nicht bei OpenAI gespeichert werden.',
                    'notice_type' => 'error',
                    'noticable_type' => \Platform\AiAssistant\Models\AiAssistantAssistant::class,
                    'noticable_id' => $this->assistant->id,
                ]);
            }
        } catch (\Exception $e) {
            $this->dispatch('notifications:store', [
                'title' => 'Fehler beim Speichern',
                'message' => 'Fehler: ' . $e->getMessage(),
                'notice_type' => 'error',
                'noticable_type' => \Platform\AiAssistant\Models\AiAssistantAssistant::class,
                'noticable_id' => $this->assistant->id,
            ]);
        }
    }

    public function createThread()
    {
        // TODO: Implement thread creation
        $this->dispatch('notifications:store', [
            'title' => 'Thread erstellen',
            'message' => 'Thread-Erstellung wird implementiert.',
            'notice_type' => 'info',
            'noticable_type' => \Platform\AiAssistant\Models\AiAssistantAssistant::class,
            'noticable_id' => $this->assistant->id,
        ]);
    }

    public function testAssistant()
    {
        // TODO: Implement assistant testing
        $this->dispatch('notifications:store', [
            'title' => 'Assistant testen',
            'message' => 'Assistant-Test wird implementiert.',
            'notice_type' => 'info',
        ]);
    }

    public function deleteAssistant()
    {
        try {
            // Löschen bei OpenAI
            $assistantService = app(\Platform\AiAssistant\Services\AssistantService::class);
            $success = $assistantService->deleteAssistant($this->assistant->openai_assistant_id);
            
            if ($success) {
                $this->assistant->delete();
                
                $this->dispatch('notifications:store', [
                    'title' => 'Assistant gelöscht',
                    'message' => 'Der Assistant wurde erfolgreich bei OpenAI und in der Datenbank gelöscht.',
                    'notice_type' => 'success',
                ]);
                
                return redirect()->route('ai-assistant.assistants.index');
            } else {
                $this->dispatch('notifications:store', [
                    'title' => 'Fehler beim Löschen',
                    'message' => 'Der Assistant konnte nicht bei OpenAI gelöscht werden.',
                    'notice_type' => 'error',
                    'noticable_type' => \Platform\AiAssistant\Models\AiAssistantAssistant::class,
                    'noticable_id' => $this->assistant->id,
                ]);
            }
        } catch (\Exception $e) {
            $this->dispatch('notifications:store', [
                'title' => 'Fehler beim Löschen',
                'message' => 'Fehler: ' . $e->getMessage(),
                'notice_type' => 'error',
                'noticable_type' => \Platform\AiAssistant\Models\AiAssistantAssistant::class,
                'noticable_id' => $this->assistant->id,
            ]);
        }
    }

    public function viewThread($threadId)
    {
        return redirect()->route('ai-assistant.threads.show', ['thread' => $threadId]);
    }

    public function render()
    {
        // Load models for dropdown
        $modelService = app(OpenAIModelService::class);
        $models = $modelService->getAvailableModels(auth()->user()->current_team_id);

        $ownershipTypes = collect([
            ['id' => 'team', 'name' => 'Team (Geteilt)'],
            ['id' => 'user', 'name' => 'Privat (Nur ich)']
        ]);

        // Prüfe ob das aktuelle Model reasoning_effort unterstützt
        $currentModel = $models->firstWhere('model_id', $this->assistant->model?->model_id);
        $supportsReasoningEffort = $currentModel && $this->isReasoningEffortSupported($currentModel->model_id);

        return view('ai-assistant::livewire.assistant.show', [
            'assistant' => $this->assistant->load(['model', 'threads']),
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
