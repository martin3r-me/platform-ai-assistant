<div class="d-flex h-full">
    <!-- Linke Spalte -->
    <div class="flex-grow-1 d-flex flex-col">
        <!-- Header oben (fix) -->
        <div class="border-top-1 border-bottom-1 border-muted border-top-solid border-bottom-solid p-2 flex-shrink-0">
            <div class="d-flex gap-1">
                <div class="d-flex">
                    <a href="{{ route('ai-assistant.assistants.index') }}" class="d-flex px-3 border-right-solid border-right-1 border-right-muted underline" wire:navigate>
                        AI Assistants
                    </a>
                </div>
                <div class="flex-grow-1 text-right d-flex items-center justify-end gap-2">
                    <span>{{ $assistant->name }}</span>
                    @if($this->isDirty)
                        <x-ui-button 
                            variant="primary" 
                            size="sm"
                            wire:click="save"
                        >
                            <div class="d-flex items-center gap-2">
                                @svg('heroicon-o-check', 'w-4 h-4')
                                Speichern
                            </div>
                        </x-ui-button>
                    @endif
                </div>
            </div>
        </div>

        <!-- Haupt-Content (nimmt Restplatz, scrollt) -->
        <div class="flex-grow-1 overflow-y-auto p-4">
            
            {{-- Assistant Details --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4 text-secondary">Assistant Details</h3>
                <div class="grid grid-cols-2 gap-4">
                    <x-ui-input-text 
                        name="assistant.name"
                        label="Name"
                        wire:model.live.debounce.500ms="assistant.name"
                        placeholder="Assistant Name..."
                        required
                        :errorKey="'assistant.name'"
                    />
                    <x-ui-input-select
                        name="assistant.ai_model_id"
                        label="OpenAI Model"
                        :options="$models"
                        optionValue="id"
                        optionLabel="display_name"
                        :nullable="true"
                        nullLabel="– Model auswählen –"
                        wire:model.live="assistant.ai_model_id"
                        required
                    />
                    @if($assistant->ai_model_id)
                        <div class="text-xs text-muted mt-1">
                            Aktuell: {{ $assistant->model?->display_name ?? 'Unbekanntes Model' }}
                        </div>
                    @endif
                </div>
                <div class="mt-4">
                    <x-ui-input-text 
                        name="assistant.description"
                        label="Beschreibung"
                        wire:model.live.debounce.500ms="assistant.description"
                        placeholder="Kurze Beschreibung des Assistants"
                        :errorKey="'assistant.description'"
                    />
                </div>
                <div class="mt-4">
                    <x-ui-input-textarea 
                        name="assistant.instructions"
                        label="Anweisungen"
                        wire:model.live.debounce.500ms="assistant.instructions"
                        placeholder="Beschreiben Sie die Rolle und das Verhalten des Assistants..."
                        rows="6"
                        required
                        :errorKey="'assistant.instructions'"
                    />
                </div>
            </div>

            {{-- Model Configuration --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4 text-secondary">Model Konfiguration</h3>
                <div class="grid grid-cols-3 gap-4">
                    <x-ui-input-select
                        name="assistant.response_format"
                        label="Response Format"
                        :options="collect([
                            ['id' => 'auto', 'name' => 'Auto'],
                            ['id' => 'json_object', 'name' => 'JSON Object']
                        ])"
                        optionValue="id"
                        optionLabel="name"
                        wire:model.live="assistant.response_format"
                    />

                    @if($supportsReasoningEffort)
                        <x-ui-input-select
                            name="assistant.reasoning_effort"
                            label="Reasoning Effort"
                            :options="collect([
                                ['id' => 'minimal', 'name' => 'Minimal'],
                                ['id' => 'low', 'name' => 'Low'],
                                ['id' => 'medium', 'name' => 'Medium'],
                                ['id' => 'high', 'name' => 'High']
                            ])"
                            optionValue="id"
                            optionLabel="name"
                            wire:model.live="assistant.reasoning_effort"
                        />
                    @else
                        <div class="text-sm text-muted d-flex items-center">
                            <span>Reasoning Effort nicht verfügbar für dieses Model</span>
                        </div>
                    @endif

                    <x-ui-input-text
                        name="assistant.temperature"
                        label="Temperature"
                        type="number"
                        step="0.1"
                        min="0"
                        max="2"
                        wire:model.live.debounce.500ms="assistant.temperature"
                        placeholder="1.0"
                    />
                </div>
                <div class="mt-4">
                    <x-ui-input-text
                        name="assistant.top_p"
                        label="Top P"
                        type="number"
                        step="0.1"
                        min="0"
                        max="1"
                        wire:model.live.debounce.500ms="assistant.top_p"
                        placeholder="1.0"
                    />
                </div>
            </div>

            {{-- Besitz & Status --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4 text-secondary">Besitz & Status</h3>
                <div class="grid grid-cols-2 gap-4">
                    <x-ui-input-select
                        name="assistant.ownership_type"
                        label="Besitz"
                        :options="$ownershipTypes"
                        optionValue="id"
                        optionLabel="name"
                        :nullable="false"
                        wire:model.live="assistant.ownership_type"
                        required
                    />
                    <x-ui-input-checkbox
                        model="assistant.is_active"
                        checked-label="Assistant ist aktiv"
                        unchecked-label="Assistant ist inaktiv"
                        size="md"
                        block="true"
                    />
                </div>
            </div>

            {{-- Tools & Metadaten --}}
            <div class="mb-6">
                <h3 class="text-lg font-semibold mb-4 text-secondary">Tools & Metadaten</h3>
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                    <h4 class="font-medium text-gray-900 mb-2">Tools (Optional)</h4>
                    <p class="text-gray-600 text-sm mb-3">Tools werden später implementiert...</p>
                    <div class="text-sm text-gray-500">
                        • Function Calling<br>
                        • Code Interpreter<br>
                        • Retrieval<br>
                        • Custom Tools
                    </div>
                </div>
                <div class="mt-4">
                    <x-ui-input-textarea 
                        name="assistant.tools"
                        label="Tools (JSON)"
                        wire:model.live.debounce.500ms="assistant.tools"
                        placeholder='[{"type": "code_interpreter"}]'
                        rows="3"
                        :errorKey="'assistant.tools'"
                    />
                </div>
                <div class="mt-4">
                    <x-ui-input-textarea 
                        name="assistant.tool_resources"
                        label="Tool Resources (JSON)"
                        wire:model.live.debounce.500ms="assistant.tool_resources"
                        placeholder='{"code_interpreter": {"file_ids": ["file-123"]}}'
                        rows="3"
                        :errorKey="'assistant.tool_resources'"
                    />
                </div>
                <div class="mt-4">
                    <x-ui-input-textarea 
                        name="assistant.metadata"
                        label="Metadaten (JSON)"
                        wire:model.live.debounce.500ms="assistant.metadata"
                        placeholder='{"key": "value"}'
                        rows="3"
                        :errorKey="'assistant.metadata'"
                    />
                </div>
            </div>
        </div>

        <!-- Threads (immer unten) -->
        <div x-data="{ open: false }" class="flex-shrink-0 border-t border-muted">
            <div 
                @click="open = !open" 
                class="cursor-pointer border-top-1 border-top-solid border-top-muted border-bottom-1 border-bottom-solid border-bottom-muted p-2 text-center d-flex items-center justify-center gap-1 mx-2 shadow-lg"
            >
                THREADS 
                <span class="text-xs">
                    {{$assistant->threads->count()}}
                </span>
                <x-heroicon-o-chevron-double-down 
                    class="w-3 h-3" 
                    x-show="!open"
                />
                <x-heroicon-o-chevron-double-up 
                    class="w-3 h-3" 
                    x-show="open"
                />
            </div>
            <div x-show="open" class="p-2 max-h-xs overflow-y-auto">
                @forelse($assistant->threads as $thread)
                    <div class="d-flex items-center gap-2 p-2 bg-muted-5 rounded mb-2">
                        <div class="flex-grow-1">
                            <div class="text-sm font-medium">
                                <a href="{{ route('ai-assistant.threads.show', ['thread' => $thread->id]) }}" 
                                   class="hover:underline text-primary" 
                                   wire:navigate>
                                    Thread #{{ $thread->id }}
                                </a>
                            </div>
                            <div class="text-xs text-muted">
                                {{ $thread->created_at->format('d.m.Y H:i') }}
                                @if($thread->context_type)
                                    - {{ class_basename($thread->context_type) }}
                                @endif
                            </div>
                        </div>
                        <x-ui-badge variant="primary" size="xs">{{ $thread->status }}</x-ui-badge>
                    </div>
                @empty
                    <p class="text-sm text-muted text-center py-4">Noch keine Threads vorhanden.</p>
                @endforelse
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

            {{-- Navigation Buttons --}}
            <div class="d-flex flex-col gap-2 mb-4">
                <x-ui-button 
                    variant="secondary-outline" 
                    size="md" 
                    :href="route('ai-assistant.assistants.index')" 
                    wire:navigate
                    class="w-full"
                >
                    <div class="d-flex items-center gap-2">
                        @svg('heroicon-o-arrow-left', 'w-4 h-4')
                        Zurück zu Assistants
                    </div>
                </x-ui-button>
            </div>

            {{-- Kurze Übersicht --}}
            <div class="mb-4 p-3 bg-muted-5 rounded-lg">
                <h4 class="font-semibold mb-2 text-secondary">Assistant-Übersicht</h4>
                <div class="space-y-1 text-sm">
                    <div><strong>Name:</strong> {{ $assistant->name }}</div>
                    @if($assistant->model)
                        <div><strong>Model:</strong> {{ $assistant->model->model_id }}</div>
                        <div><strong>Model Name:</strong> {{ $assistant->model->name }}</div>
                    @else
                        <div><strong>Model:</strong> <span class="text-red-500">Kein Model zugewiesen</span></div>
                    @endif
                    @if($assistant->openai_assistant_id)
                        <div><strong>OpenAI ID:</strong> {{ Str::limit($assistant->openai_assistant_id, 20) }}</div>
                    @endif
                    <div><strong>Besitz:</strong> {{ $assistant->ownership_type === 'team' ? 'Team' : 'Privat' }}</div>
                    <div><strong>Status:</strong> {{ $assistant->is_active ? 'Aktiv' : 'Inaktiv' }}</div>
                </div>
            </div>

            <hr>

            {{-- Threads --}}
            <div class="mb-4">
                <h4 class="font-semibold mb-2">Aktuelle Threads</h4>
                <div class="space-y-2">
                    @foreach($assistant->threads->take(5) as $thread)
                        <div class="d-flex items-center gap-2 p-2 bg-muted-5 rounded cursor-pointer" wire:click="viewThread({{ $thread->id }})">
                            <div class="flex-grow-1">
                                <div class="text-sm font-medium">
                                    <a href="{{ route('ai-assistant.threads.show', ['thread' => $thread->id]) }}" 
                                       class="hover:underline text-primary" 
                                       wire:navigate
                                       @click.stop>
                                        Thread #{{ $thread->id }}
                                    </a>
                                </div>
                                <div class="text-xs text-muted">
                                    {{ $thread->created_at->format('d.m.Y H:i') }}
                                    @if($thread->context_type)
                                        - {{ class_basename($thread->context_type) }}
                                    @endif
                                </div>
                            </div>
                            <x-ui-badge variant="primary" size="xs">{{ $thread->status }}</x-ui-badge>
                        </div>
                    @endforeach
                    @if($assistant->threads->count() === 0)
                        <p class="text-sm text-muted">Noch keine Threads vorhanden.</p>
                    @endif
                    @if($assistant->threads->count() > 5)
                        <div class="text-xs text-muted text-center">
                            +{{ $assistant->threads->count() - 5 }} weitere Threads
                        </div>
                    @endif
                </div>
            </div>

            <hr>

            {{-- Statistiken --}}
            <div class="mb-4">
                <h4 class="font-semibold mb-2">Statistiken</h4>
                <div class="space-y-2 text-sm">
                    <div class="d-flex justify-between">
                        <span>Threads gesamt:</span>
                        <span class="font-medium">{{ $assistant->threads->count() }}</span>
                    </div>
                    <div class="d-flex justify-between">
                        <span>Aktive Threads:</span>
                        <span class="font-medium">{{ $assistant->threads->where('status', 'active')->count() }}</span>
                    </div>
                    <div class="d-flex justify-between">
                        <span>Erstellt am:</span>
                        <span class="font-medium">{{ $assistant->created_at->format('d.m.Y') }}</span>
                    </div>
                    <div class="d-flex justify-between">
                        <span>Zuletzt aktualisiert:</span>
                        <span class="font-medium">{{ $assistant->updated_at->format('d.m.Y') }}</span>
                    </div>
                </div>
            </div>

            <hr>

            {{-- Aktionen --}}
            <div class="mb-4">
                <h4 class="font-semibold mb-2">Aktionen</h4>
                <div class="space-y-2">
                    <x-ui-button 
                        size="sm" 
                        variant="secondary-outline" 
                        wire:click="createThread"
                        class="w-full"
                    >
                        <div class="d-flex items-center gap-2">
                            @svg('heroicon-o-plus', 'w-4 h-4')
                            Neuen Thread erstellen
                        </div>
                    </x-ui-button>
                    
                    <x-ui-button 
                        size="sm" 
                        variant="secondary-outline" 
                        wire:click="testAssistant"
                        class="w-full"
                    >
                        <div class="d-flex items-center gap-2">
                            @svg('heroicon-o-play', 'w-4 h-4')
                            Assistant testen
                        </div>
                    </x-ui-button>
                    
                    <x-ui-confirm-button 
                        action="deleteAssistant" 
                        text="Assistant löschen" 
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
