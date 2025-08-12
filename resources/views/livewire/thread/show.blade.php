<div class="d-flex h-full">
    <!-- Linke Spalte -->
    <div class="flex-grow-1 d-flex flex-col">
        <!-- Header oben (fix) -->
        <div class="border-top-1 border-bottom-1 border-muted border-top-solid border-bottom-solid p-2 flex-shrink-0">
            <div class="d-flex gap-1">
                <div class="d-flex">
                    <a href="{{ route('ai-assistant.threads.index') }}" class="d-flex px-3 border-right-solid border-right-1 border-right-muted underline" wire:navigate>
                        AI Threads
                    </a>
                </div>
                <div class="flex-grow-1 text-right d-flex items-center justify-end gap-2">
                    <x-ui-button variant="secondary-outline" size="sm" wire:click="refreshMessages">
                        <div class="d-flex items-center gap-2">
                            @svg('heroicon-o-arrow-path', 'w-4 h-4')
                            Aktualisieren
                        </div>
                    </x-ui-button>
                </div>
            </div>
        </div>

        <!-- Chat Messages (scrollbar) -->
        <div class="flex-grow-1 overflow-y-auto p-4 space-y-4" wire:poll.2s="refreshMessages" id="messages-container">
            @forelse($this->messages as $message)
                <div class="d-flex {{ $message->role === 'user' ? 'justify-end' : 'justify-start' }}">
                    <div class="max-w-md lg:max-w-2xl xl:max-w-3xl">
                        <div class="d-flex items-center gap-2 mb-1">
                            <span class="text-xs font-medium text-gray-500">
                                {{ $message->role === 'user' ? 'Sie' : 'AI Assistant' }}
                            </span>
                            <span class="text-xs text-gray-400">
                                {{ $message->created_at->format('H:i') }}
                            </span>
                        </div>
                        
                        <div class="p-4 rounded-lg {{ $message->role === 'user' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-900' }}">
                            <div class="prose prose-sm max-w-none">
                                {!! Str::markdown($message->content) !!}
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-8">
                    <x-heroicon-o-chat-bubble-left-right class="w-12 h-12 mx-auto mb-4 text-gray-400" />
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Keine Nachrichten</h3>
                    <p class="text-gray-600">Starten Sie die Konversation</p>
                </div>
            @endforelse

            {{-- Wartestatus anzeigen wenn aktiver Run existiert --}}
            @if($this->hasActiveRun)
                <div class="d-flex justify-start">
                    <div class="max-w-md lg:max-w-2xl xl:max-w-3xl">
                        <div class="d-flex items-center gap-2 mb-1">
                            <span class="text-xs font-medium text-gray-500">AI Assistant</span>
                            <span class="text-xs text-gray-400">Wird verarbeitet...</span>
                        </div>
                        
                        <div class="p-4 rounded-lg bg-gray-100 text-gray-900">
                            <div class="d-flex items-center gap-2">
                                <x-heroicon-o-arrow-path class="w-4 h-4 animate-spin text-gray-500" />
                                <span class="text-sm text-gray-600">
                                    @switch($this->activeRunStatus)
                                        @case('queued')
                                            Nachricht wird verarbeitet...
                                            @break
                                        @case('in_progress')
                                            AI Assistant denkt nach...
                                            @break
                                        @case('requires_action')
                                            AI Assistant führt Aktionen aus...
                                            @break
                                        @default
                                            Wird verarbeitet...
                                    @endswitch
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Chat Input (immer unten, fix) -->
        <div class="flex-shrink-0 border-t border-muted" x-data="chatInput()">
            <div class="border-top-1 border-top-solid border-top-muted border-bottom-1 border-bottom-solid border-bottom-muted p-2 d-flex items-center justify-between mx-2 shadow-lg">
                {{-- Links: Run Badges --}}
                <div class="d-flex items-center gap-2">
                    <x-ui-badge variant="secondary" size="xs">
                        {{$this->runs->count()}} Runs
                    </x-ui-badge>
                    @if($this->hasActiveRun)
                        <x-ui-badge variant="warning" size="xs">Aktiv</x-ui-badge>
                    @endif
                </div>
            </div>
                        <div class="p-2">
                @if($this->hasActiveRun)
                    {{-- Input deaktiviert während aktiver Run --}}
                    <div class="d-flex gap-2">
                        <div class="flex-grow-1">
                            <textarea 
                                placeholder="Bitte warten Sie auf die Antwort..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100 text-gray-500 cursor-not-allowed resize-none"
                                rows="1"
                                disabled
                            ></textarea>
                        </div>
                        <x-ui-button 
                            type="button" 
                            variant="secondary" 
                            size="md"
                            disabled
                        >
                            <x-heroicon-o-arrow-path class="w-4 h-4 animate-spin" />
                        </x-ui-button>
                    </div>
                @else
                    {{-- Normaler Input --}}
                    <form wire:submit.prevent="sendMessage" class="d-flex gap-2">
                        <div class="flex-grow-1" wire:ignore>
                            <textarea 
                                x-ref="textarea"
                                wire:model.live="newMessage"
                                placeholder="Nachricht eingeben..."
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent resize-none"
                                rows="1"
                                style="min-height: 40px; max-height: 120px; overflow-y: auto;"
                                @input="resizeTextarea()"
                                @keydown.enter="handleEnter($event)"
                            ></textarea>
                        </div>
                        <x-ui-button 
                            type="submit" 
                            variant="primary" 
                            size="md"
                            class="self-end"
                            x-ref="sendButton"
                        >
                            <x-heroicon-o-paper-airplane class="w-4 h-4" />
                        </x-ui-button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <!-- Rechte Spalte -->
    <div class="min-w-80 w-80 d-flex flex-col border-left-1 border-left-solid border-left-muted">

        <div class="d-flex gap-2 border-top-1 border-bottom-1 border-muted border-top-solid border-bottom-solid p-2 flex-shrink-0">
            <x-heroicon-o-cog-6-tooth class="w-6 h-6"/>
            Einstellungen
        </div>
        <div class="flex-grow-1 overflow-y-auto p-4">

            {{-- Thread Titel --}}
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-secondary">{{ $thread->title }}</h3>
            </div>

            {{-- Navigation Buttons --}}
            <div class="d-flex flex-col gap-2 mb-4">
                <x-ui-button 
                    variant="secondary-outline" 
                    size="md" 
                    :href="route('ai-assistant.threads.index')" 
                    wire:navigate
                    class="w-full"
                >
                    <div class="d-flex items-center gap-2">
                        @svg('heroicon-o-arrow-left', 'w-4 h-4')
                        Zurück zu Threads
                    </div>
                </x-ui-button>
            </div>

            {{-- Thread Settings --}}
            <div class="mb-4">
                <h4 class="font-semibold mb-2">Thread Einstellungen</h4>
                <div class="space-y-3">
                    <x-ui-input-text 
                        name="thread.title"
                        label="Titel"
                        wire:model.live.debounce.500ms="thread.title"
                        placeholder="Thread-Titel..."
                    />
                    
                    <x-ui-input-select
                        name="thread.status"
                        label="Status"
                        :options="collect([
                            ['id' => 'active', 'name' => 'Aktiv'],
                            ['id' => 'completed', 'name' => 'Abgeschlossen'],
                            ['id' => 'archived', 'name' => 'Archiviert']
                        ])"
                        optionValue="id"
                        optionLabel="name"
                        wire:model.live="thread.status"
                    />

                    <x-ui-input-select
                        name="thread.ai_model_id"
                        label="AI Model"
                        :options="$models"
                        optionValue="id"
                        optionLabel="display_name"
                        wire:model.live="thread.ai_model_id"
                        :nullable="true"
                        nullLabel="– Model vom Assistant übernehmen –"
                    />
                </div>
            </div>

            <hr>

            {{-- Kurze Übersicht --}}
            <div class="mb-4 p-3 bg-muted-5 rounded-lg">
                <h4 class="font-semibold mb-2 text-secondary">Thread-Übersicht</h4>
                <div class="space-y-1 text-sm">
                    <div><strong>Titel:</strong> {{ $thread->title }}</div>
                    @if($thread->assistant)
                        <div><strong>Assistant:</strong> {{ $thread->assistant->name }}</div>
                    @else
                        <div><strong>Assistant:</strong> <span class="text-red-500">Kein Assistant</span></div>
                    @endif
                    @if($thread->model)
                        <div><strong>Model:</strong> {{ $thread->model->model_id }}</div>
                        <div><strong>Model Name:</strong> {{ $thread->model->name }}</div>
                    @else
                        <div><strong>Model:</strong> <span class="text-red-500">Kein Model</span></div>
                    @endif
                    @if($thread->openai_thread_id)
                        <div><strong>OpenAI ID:</strong> {{ Str::limit($thread->openai_thread_id, 20) }}</div>
                    @endif
                    <div><strong>Besitz:</strong> {{ $thread->ownership_type === 'team' ? 'Team' : 'Privat' }}</div>
                    <div><strong>Status:</strong> {{ ucfirst($thread->status) }}</div>
                </div>
            </div>

            <hr>

            {{-- Statistiken --}}
            <div class="mb-4">
                <h4 class="font-semibold mb-2">Statistiken</h4>
                <div class="space-y-2 text-sm">
                    <div class="d-flex justify-between">
                        <span>Nachrichten:</span>
                        <span class="font-medium">{{ $this->messages->count() }}</span>
                    </div>
                    <div class="d-flex justify-between">
                        <span>Runs:</span>
                        <span class="font-medium">{{ $this->runs->count() }}</span>
                    </div>
                    <div class="d-flex justify-between">
                        <span>Aktive Runs:</span>
                        <span class="font-medium">{{ $this->hasActiveRun ? '1' : '0' }}</span>
                    </div>
                    <div class="d-flex justify-between">
                        <span>Erstellt am:</span>
                        <span class="font-medium">{{ $thread->created_at->format('d.m.Y') }}</span>
                    </div>
                    <div class="d-flex justify-between">
                        <span>Zuletzt aktualisiert:</span>
                        <span class="font-medium">{{ $thread->updated_at->format('d.m.Y') }}</span>
                    </div>
                </div>
            </div>

            <hr>

            {{-- Runs (aufklappbar) --}}
            <div x-data="{ open: false }" class="mb-4">
                <div 
                    @click="open = !open" 
                    class="cursor-pointer p-2 text-center d-flex items-center justify-center gap-1 border border-gray-200 rounded-lg shadow-sm"
                >
                    RUNS 
                    <span class="text-xs">
                        {{$this->runs->count()}}
                    </span>
                    @if($this->hasActiveRun)
                        <x-ui-badge variant="warning" size="xs">Aktiv</x-ui-badge>
                    @endif
                    <x-heroicon-o-chevron-double-down 
                        class="w-3 h-3" 
                        x-show="!open"
                    />
                    <x-heroicon-o-chevron-double-up 
                        class="w-3 h-3" 
                        x-show="open"
                    />
                </div>
                <div x-show="open" class="mt-2 max-h-64 overflow-y-auto">
                    @if($this->runs->count() > 0)
                        <div class="space-y-2">
                            @foreach($this->runs->take(10) as $run)
                                <div class="p-2 bg-gray-50 rounded text-sm">
                                    <div class="d-flex justify-between items-start">
                                        <div class="flex-grow-1">
                                            <div class="font-medium">Run #{{ $run->id }}</div>
                                            <div class="text-xs text-gray-500">
                                                {{ $run->started_at?->format('d.m.Y H:i') }}
                                                @if($run->completed_at)
                                                    - {{ $run->completed_at->format('d.m.Y H:i') }}
                                                @endif
                                            </div>
                                        </div>
                                        <div class="d-flex gap-1">
                                            @if($run->status === 'completed')
                                                <x-ui-badge variant="success" size="xs">✓</x-ui-badge>
                                            @elseif($run->status === 'failed')
                                                <x-ui-badge variant="danger" size="xs">✗</x-ui-badge>
                                            @else
                                                <x-ui-badge variant="warning" size="xs">{{ ucfirst($run->status) }}</x-ui-badge>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-muted text-center py-2">Noch keine Runs vorhanden.</p>
                    @endif
                </div>
            </div>

            <hr>

            {{-- Kontext --}}
            @if($thread->context)
                <div class="mb-4">
                    <h4 class="font-semibold mb-2">Kontext</h4>
                    <div class="p-2 bg-muted-5 rounded text-sm">
                        <div><strong>Typ:</strong> {{ class_basename($thread->context_type) }}</div>
                        <div><strong>ID:</strong> {{ $thread->context_id }}</div>
                    </div>
                </div>
                <hr>
            @endif

            {{-- Aktionen --}}
            <div class="mb-4">
                <h4 class="font-semibold mb-2">Aktionen</h4>
                <div class="space-y-2">
                    <x-ui-button 
                        size="sm" 
                        variant="secondary-outline" 
                        wire:click="refreshMessages"
                        class="w-full"
                    >
                        <div class="d-flex items-center gap-2">
                            @svg('heroicon-o-arrow-path', 'w-4 h-4')
                            Nachrichten aktualisieren
                        </div>
                    </x-ui-button>
                    
                    <x-ui-confirm-button 
                        action="deleteThread" 
                        text="Thread löschen" 
                        confirmText="Wirklich löschen? Dieser Vorgang kann nicht rückgängig gemacht werden." 
                        variant="danger-outline"
                        class="w-full"
                        :icon="@svg('heroicon-o-trash', 'w-4 h-4')->toHtml()"
                    />
                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Alpine.js Chat Input Component
function chatInput() {
    return {
        init() {
            this.resizeTextarea();
            this.scrollToBottom();
        },
        
        resizeTextarea() {
            const textarea = this.$refs.textarea;
            if (!textarea) return;
            
            // Reset height to auto to get correct scrollHeight
            textarea.style.height = 'auto';
            
            // Calculate new height
            const newHeight = Math.min(textarea.scrollHeight, 120);
            textarea.style.height = newHeight + 'px';
            
            // Scroll to bottom when textarea grows
            if (newHeight > 40) {
                this.scrollToBottom();
            }
        },
        
        sendMessage() {
            // Trigger Livewire sendMessage
            this.$wire.sendMessage();
            
            // Reset textarea height (content is cleared by Livewire)
            this.resizeTextarea();
        },
        
        handleEnter(event) {
            if (event.shiftKey) {
                // Shift+Enter = Neue Zeile
                return;
            } else {
                // Enter = Senden
                event.preventDefault();
                
                // Prüfen ob Nachricht nicht leer ist
                if (this.$wire.newMessage.trim()) {
                    this.sendMessage();
                }
            }
        },
        
        scrollToBottom() {
            const container = document.getElementById('messages-container');
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        }
    }
}

// Livewire Integration
document.addEventListener('livewire:init', () => {
    let shouldAutoScroll = true;
    const container = document.getElementById('messages-container');
    
    // Initial nach unten scrollen
    if (container) {
        setTimeout(() => {
            container.scrollTop = container.scrollHeight;
        }, 100);
    }
    
    // Prüfen ob User am Ende scrollt
    if (container) {
        container.addEventListener('scroll', () => {
            const { scrollTop, scrollHeight, clientHeight } = container;
            shouldAutoScroll = scrollTop + clientHeight >= scrollHeight - 10;
        });
    }
    
    Livewire.on('$refresh', () => {
        // Nur scrollen wenn User am Ende ist oder neue Messages
        if (shouldAutoScroll && container) {
            setTimeout(() => {
                container.scrollTop = container.scrollHeight;
            }, 100);
        }
    });
});
</script>


