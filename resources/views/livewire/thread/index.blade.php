<div class="p-3">
    <h1 class="text-2xl font-bold mb-4">AI Threads</h1>

    <div class="d-flex justify-between mb-4">
        <x-ui-input-text 
            name="search" 
            placeholder="Suche Threads..." 
            class="w-64"
            wire:model.live.debounce.300ms="search"
        />
        <x-ui-button variant="primary" wire:click="openCreateModal">
            Neuer Thread
        </x-ui-button>
    </div>

    <x-ui-table compact="true">
        <x-ui-table-header>
            <x-ui-table-header-cell compact="true" sortable="true" sortField="title" :currentSort="$sortBy" :sortDirection="$sortDirection">Titel</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true">Assistant</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true">Model</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true">Nachrichten</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true" sortable="true" sortField="ownership_type" :currentSort="$sortBy" :sortDirection="$sortDirection">Besitz</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true" align="right">Aktionen</x-ui-table-header-cell>
        </x-ui-table-header>
        
        <x-ui-table-body>
            @forelse($threads as $thread)
                <x-ui-table-row 
                    compact="true"
                    clickable="true" 
                    :href="route('ai-assistant.threads.show', ['thread' => $thread->id])"
                >
                    <x-ui-table-cell compact="true">
                        <div class="font-medium">{{ $thread->title }}</div>
                        @if($thread->openai_thread_id)
                            <div class="text-xs text-muted">ID: {{ Str::limit($thread->openai_thread_id, 20) }}</div>
                        @endif
                    </x-ui-table-cell>
                    <x-ui-table-cell compact="true">
                        @if($thread->assistant)
                            <div class="text-sm">{{ $thread->assistant->name }}</div>
                        @else
                            <div class="text-xs text-muted">Kein Assistant</div>
                        @endif
                    </x-ui-table-cell>
                    <x-ui-table-cell compact="true">
                        <div class="space-y-1">
                            <div class="text-xs d-flex items-center gap-1">
                                @svg('heroicon-o-cpu-chip', 'w-3 h-3 text-muted')
                                {{ $thread->model?->model_id ?? 'Kein Model' }}
                            </div>
                            @if($thread->model?->name)
                                <div class="text-xs text-muted">{{ $thread->model->name }}</div>
                            @endif
                        </div>
                    </x-ui-table-cell>
                    <x-ui-table-cell compact="true">
                        <div class="text-sm">{{ $thread->messages->count() }} Nachrichten</div>
                        <div class="text-xs text-muted">{{ $thread->runs->count() }} Runs</div>
                    </x-ui-table-cell>
                    <x-ui-table-cell compact="true">
                        <div class="d-flex items-center gap-1">
                            @if($thread->ownership_type === 'team')
                                <x-ui-badge variant="primary" size="sm">Team</x-ui-badge>
                            @else
                                <x-ui-badge variant="success" size="sm">Privat</x-ui-badge>
                            @endif
                            @if($thread->status === 'active')
                                <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                            @else
                                <x-ui-badge variant="secondary" size="sm">{{ ucfirst($thread->status) }}</x-ui-badge>
                            @endif
                        </div>
                    </x-ui-table-cell>
                    <x-ui-table-cell compact="true" align="right">
                        <x-ui-button 
                            size="sm" 
                            variant="secondary" 
                            href="{{ route('ai-assistant.threads.show', ['thread' => $thread->id]) }}" 
                            wire:navigate
                        >
                            Anzeigen
                        </x-ui-button>
                    </x-ui-table-cell>
                </x-ui-table-row>
            @empty
                <x-ui-table-row compact="true">
                    <x-ui-table-cell compact="true" colspan="6">
                        <div class="text-center py-8">
                            <x-heroicon-o-chat-bubble-left-right class="w-12 h-12 mx-auto mb-4 text-muted" />
                            <h3 class="text-lg font-medium mb-2">Keine Threads gefunden</h3>
                            <p class="text-muted mb-4">Starten Sie eine neue Konversation mit einem AI Assistant</p>
                            <x-ui-button variant="primary" wire:click="openCreateModal">
                                Neuer Thread
                            </x-ui-button>
                        </div>
                    </x-ui-table-cell>
                </x-ui-table-row>
            @endforelse
        </x-ui-table-body>
    </x-ui-table>

    <!-- Pagination -->
    @if($threads->hasPages())
        <div class="mt-6">
            {{ $threads->links() }}
        </div>
    @endif

    <!-- Create Thread Modal -->
    <x-ui-modal
        wire:model="modalShow"
        size="lg"
    >
        <x-slot name="header">
            Neuen Thread erstellen
        </x-slot>

        <div class="space-y-4">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <div class="d-flex items-center gap-2 mb-2">
                    @svg('heroicon-o-information-circle', 'w-5 h-5 text-blue-600')
                    <h4 class="font-medium text-blue-900">Hinweis</h4>
                </div>
                <p class="text-blue-700 text-sm">Erstellen Sie einen neuen Thread für eine Konversation mit einem AI Assistant. Sie können später Nachrichten senden und empfangen.</p>
            </div>

            <form wire:submit.prevent="createThread" class="space-y-4">
                <x-ui-input-text
                    name="title"
                    label="Thread-Titel"
                    wire:model.live="title"
                    required
                    placeholder="z.B. Projektbesprechung, Support-Anfrage, etc."
                />

                <x-ui-input-select
                    name="assistant_id"
                    label="AI Assistant"
                    :options="$assistants"
                    optionValue="id"
                    optionLabel="name"
                    wire:model.live="assistant_id"
                    required
                />

                <x-ui-input-select
                    name="ai_model_id"
                    label="OpenAI Model (Optional)"
                    :options="$models"
                    optionValue="id"
                    optionLabel="display_name"
                    wire:model.live="ai_model_id"
                    :nullable="true"
                    nullLabel="– Model vom Assistant übernehmen –"
                />

                <x-ui-input-select
                    name="ownership_type"
                    label="Besitz"
                    :options="$ownershipTypes"
                    optionValue="id"
                    optionLabel="name"
                    wire:model.live="ownership_type"
                    required
                />

                <x-ui-input-textarea
                    name="metadata"
                    label="Metadaten (JSON, Optional)"
                    wire:model.live="metadata"
                    placeholder='{"key": "value"}'
                    rows="3"
                />
            </form>
        </div>

        <x-slot name="footer">
            <div class="d-flex justify-end gap-2">
                <x-ui-button 
                    type="button" 
                    variant="secondary-outline" 
                    @click="$wire.closeCreateModal()"
                >
                    Abbrechen
                </x-ui-button>
                <x-ui-button type="button" variant="primary" wire:click="createThread">
                    Thread erstellen
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>
</div>
