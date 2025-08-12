<?php

namespace Platform\AiAssistant\Livewire\Thread;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\AiAssistant\Models\AiAssistantThread;
use Platform\AiAssistant\Services\RunService;
use Platform\AiAssistant\Services\ThreadService;

class Show extends Component
{
    public AiAssistantThread $thread;
    public $newMessage = '';
    public $isSending = false;

    protected $rules = [
        'thread.title' => 'required|string|max:255',
        'thread.status' => 'required|in:active,completed,archived',
        'thread.ai_model_id' => 'nullable|exists:ai_assistant_models,id',
    ];

    public function mount(AiAssistantThread $thread)
    {
        $this->thread = $thread;
        
        // Check if user has access to this thread
        if (!$this->thread->isAccessibleBy(auth()->user())) {
            abort(403);
        }
    }

    public function updated($propertyName)
    {
        // Wenn sich Thread-Eigenschaften ändern, automatisch speichern
        if (str_starts_with($propertyName, 'thread.')) {
            $this->save();
        }
    }

    public function sendMessage()
    {
        if (empty(trim($this->newMessage))) {
            return;
        }

        // Prüfen ob bereits ein aktiver Run existiert
        if ($this->hasActiveRun()) {
            $this->dispatch('notifications:store', [
                'title' => 'Bitte warten',
                'message' => 'Es läuft bereits eine Anfrage. Bitte warten Sie auf die Antwort.',
                'notice_type' => 'warning',
                'noticable_type' => \Platform\Core\Models\User::class,
                'noticable_id' => auth()->id(),
            ]);
            return;
        }

        // Message-Feld SOFORT leeren (vor API-Calls)
        $messageContent = $this->newMessage;
        $this->newMessage = '';

        $this->isSending = true;

        try {
            // 1. User Message bei OpenAI erstellen
            $threadService = app(ThreadService::class);
            $messageId = $threadService->createMessage(
                $this->thread->openai_thread_id,
                'user',
                $messageContent
            );

            // 2. User Message in lokaler DB speichern
            $this->thread->messages()->create([
                'openai_message_id' => $messageId,
                'role' => 'user',
                'content' => $messageContent,
                'team_id' => auth()->user()->current_team_id,
                'created_by_user_id' => auth()->id(),
            ]);

            // 3. Run bei OpenAI erstellen
            $runService = app(RunService::class);
            $runId = $runService->createRun(
                $this->thread->openai_thread_id,
                $this->thread->assistant->openai_assistant_id
            );

            // 4. Run in lokaler DB speichern
            $this->thread->runs()->create([
                'openai_run_id' => $runId,
                'assistant_id' => $this->thread->assistant_id,
                'status' => 'queued',
                'type' => 'message_creation',
                'team_id' => auth()->user()->current_team_id,
                'created_by_user_id' => auth()->id(),
            ]);

        } catch (\Exception $e) {
            $this->dispatch('notifications:store', [
                'title' => 'Fehler beim Senden',
                'message' => 'Die Nachricht konnte nicht gesendet werden: ' . $e->getMessage(),
                'notice_type' => 'error',
                'noticable_type' => \Platform\Core\Models\User::class,
                'noticable_id' => auth()->id(),
            ]);
        } finally {
            $this->isSending = false;
        }
    }

    public function refreshMessages()
    {
        // Diese Methode wird von wire:poll aufgerufen
        // Der Scheduler kümmert sich um das Polling der Runs
        $this->dispatch('$refresh');
    }

    public function save()
    {
        $this->validate();

        try {
            $this->thread->save();

            $this->dispatch('notifications:store', [
                'title' => 'Thread gespeichert',
                'message' => 'Die Thread-Einstellungen wurden erfolgreich gespeichert.',
                'notice_type' => 'success',
                'noticable_type' => \Platform\Core\Models\User::class,
                'noticable_id' => auth()->id(),
            ]);
        } catch (\Exception $e) {
            $this->dispatch('notifications:store', [
                'title' => 'Fehler beim Speichern',
                'message' => 'Die Thread-Einstellungen konnten nicht gespeichert werden: ' . $e->getMessage(),
                'notice_type' => 'error',
                'noticable_type' => \Platform\Core\Models\User::class,
                'noticable_id' => auth()->id(),
            ]);
        }
    }

    #[Computed]
    public function hasActiveRun(): bool
    {
        return $this->thread->activeRun()->exists();
    }

    #[Computed]
    public function activeRunStatus(): ?string
    {
        $activeRun = $this->thread->activeRun()->first();
        return $activeRun ? $activeRun->status : null;
    }

    #[Computed]
    public function messages()
    {
        return $this->thread->messages()
            ->orderBy('created_at', 'asc')
            ->get();
    }

    #[Computed]
    public function runs()
    {
        return $this->thread->runs()
            ->with(['toolCalls'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);
    }

    #[Computed]
    public function models()
    {
        return \Platform\AiAssistant\Models\AiAssistantModel::active()
            ->orderBy('name')
            ->get();
    }

    public function render()
    {
        return view('ai-assistant::livewire.thread.show', [
            'thread' => $this->thread,
            'models' => $this->models,
        ])->layout('platform::layouts.app');
    }
}
