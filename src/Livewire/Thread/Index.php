<?php

namespace Platform\AiAssistant\Livewire\Thread;

use Livewire\Component;
use Livewire\WithPagination;
use Platform\AiAssistant\Models\AiAssistantThread;
use Platform\AiAssistant\Models\AiAssistantAssistant;
use Platform\AiAssistant\Services\OpenAIModelService;

class Index extends Component
{
    use WithPagination;

    public $search = '';
    public $filter = 'all'; // all, active, completed
    public $sortBy = 'created_at';
    public $sortDirection = 'desc';
    
    // Create Thread Modal
    public $modalShow = false;
    public $title = '';
    public $assistant_id = '';
    public $ai_model_id = '';
    public $metadata = '';
    public $ownership_type = 'team';

    protected $queryString = [
        'search' => ['except' => ''],
        'filter' => ['except' => 'all'],
        'sortBy' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    protected $rules = [
        'title' => 'required|string|max:255',
        'assistant_id' => 'required|exists:ai_assistant_assistants,id',
        'ai_model_id' => 'nullable|exists:ai_assistant_models,id',
        'metadata' => 'nullable|string',
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

    public function openCreateModal()
    {
        $this->modalShow = true;
        $this->resetForm();
        
        // Ersten verfügbaren Assistant vorauswählen
        $firstAssistant = AiAssistantAssistant::accessibleBy(auth()->user())
            ->where('is_active', true)
            ->orderBy('name')
            ->first();
            
        if ($firstAssistant) {
            $this->assistant_id = $firstAssistant->id;
        }
    }

    public function closeCreateModal()
    {
        $this->modalShow = false;
        $this->resetForm();
    }

    public function createThread()
    {
        $this->validate();
        
        try {
            // Assistant laden
            $assistant = AiAssistantAssistant::findOrFail($this->assistant_id);
            
            // Model ID setzen (entweder explizit oder vom Assistant)
            $modelId = $this->ai_model_id ?: $assistant->ai_model_id;
            
            // Thread bei OpenAI erstellen
            $threadService = app(\Platform\AiAssistant\Services\ThreadService::class);
            $openaiThreadId = $threadService->createThread([
                'metadata' => $this->metadata ? json_decode($this->metadata, true) : null,
            ]);
            
            // Thread in lokaler DB erstellen
            $thread = AiAssistantThread::create([
                'openai_thread_id' => $openaiThreadId,
                'title' => $this->title,
                'assistant_id' => $this->assistant_id,
                'ai_model_id' => $modelId,
                'metadata' => $this->metadata ? json_decode($this->metadata, true) : null,
                'ownership_type' => $this->ownership_type,
                'team_id' => auth()->user()->current_team_id,
                'created_by_user_id' => auth()->id(),
                'user_id' => $this->ownership_type === 'user' ? auth()->id() : null,
            ]);
            
            $this->closeCreateModal();
            
            $this->dispatch('notifications:store', [
                'title' => 'Thread erstellt',
                'message' => 'Der Chat-Thread wurde erfolgreich bei OpenAI und in der Datenbank erstellt.',
                'notice_type' => 'success',
                'noticable_type' => AiAssistantThread::class,
                'noticable_id' => $thread->id,
            ]);
            
            // Zur Thread-Show-Seite weiterleiten
            return redirect()->route('ai-assistant.threads.show', ['thread' => $thread]);
            
        } catch (\Exception $e) {
            $this->dispatch('notifications:store', [
                'title' => 'Fehler beim Erstellen',
                'message' => 'Der Thread konnte nicht erstellt werden: ' . $e->getMessage(),
                'notice_type' => 'error',
                'noticable_type' => \Platform\Core\Models\User::class,
                'noticable_id' => auth()->id(),
            ]);
        }
    }

    public function resetForm()
    {
        $this->title = '';
        $this->assistant_id = '';
        $this->ai_model_id = '';
        $this->metadata = '';
        $this->ownership_type = 'team';
    }

    public function render()
    {
        $threads = AiAssistantThread::query()
            ->accessibleBy(auth()->user())
            ->with(['assistant', 'context'])
            ->when($this->search, function ($query) {
                $query->whereHas('assistant', function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->filter === 'active', function ($query) {
                $query->where('status', 'active');
            })
            ->when($this->filter === 'completed', function ($query) {
                $query->where('status', 'completed');
            })
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(15);

        // Load assistants and models for create modal
        $assistants = AiAssistantAssistant::accessibleBy(auth()->user())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $modelService = app(OpenAIModelService::class);
        $models = $modelService->getAvailableModels(auth()->user()->current_team_id);

        $ownershipTypes = collect([
            ['id' => 'team', 'name' => 'Team (Geteilt)'],
            ['id' => 'user', 'name' => 'Privat (Nur ich)']
        ]);

        return view('ai-assistant::livewire.thread.index', [
            'threads' => $threads,
            'assistants' => $assistants,
            'models' => $models,
            'ownershipTypes' => $ownershipTypes,
        ])->layout('platform::layouts.app');
    }
}
