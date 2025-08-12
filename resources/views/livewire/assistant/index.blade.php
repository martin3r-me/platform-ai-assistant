<div class="p-3">
    <h1 class="text-2xl font-bold mb-4">AI Assistants</h1>

    <div class="d-flex justify-between mb-4">
        <x-ui-input-text 
            name="search" 
            placeholder="Suche Assistants..." 
            class="w-64"
            wire:model.live.debounce.300ms="search"
        />
        <x-ui-button variant="primary" wire:click="openCreateModal">
            Neuer Assistant
        </x-ui-button>
    </div>

    <x-ui-table compact="true">
        <x-ui-table-header>
            <x-ui-table-header-cell compact="true" sortable="true" sortField="name" :currentSort="$sortBy" :sortDirection="$sortDirection">Name</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true">Model</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true">Anweisungen</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true" sortable="true" sortField="ownership_type" :currentSort="$sortBy" :sortDirection="$sortDirection">Besitz</x-ui-table-header-cell>
            <x-ui-table-header-cell compact="true" align="right">Aktionen</x-ui-table-header-cell>
        </x-ui-table-header>
        
        <x-ui-table-body>
            @forelse($assistants as $assistant)
                <x-ui-table-row 
                    compact="true"
                    clickable="true" 
                    :href="route('ai-assistant.assistants.show', ['assistant' => $assistant->id])"
                >
                    <x-ui-table-cell compact="true">
                        <div class="font-medium">{{ $assistant->name }}</div>
                        @if($assistant->openai_assistant_id)
                            <div class="text-xs text-muted">ID: {{ Str::limit($assistant->openai_assistant_id, 20) }}</div>
                        @endif
                    </x-ui-table-cell>
                    <x-ui-table-cell compact="true">
                        <div class="space-y-1">
                            <div class="text-xs d-flex items-center gap-1">
                                @svg('heroicon-o-cpu-chip', 'w-3 h-3 text-muted')
                                {{ $assistant->model?->model_id ?? 'Kein Model' }}
                            </div>
                            @if($assistant->model?->name)
                                <div class="text-xs text-muted">{{ $assistant->model->name }}</div>
                            @endif
                        </div>
                    </x-ui-table-cell>
                    <x-ui-table-cell compact="true">
                        <div class="text-xs text-muted line-clamp-2">
                            {{ Str::limit($assistant->instructions, 100) }}
                        </div>
                    </x-ui-table-cell>
                    <x-ui-table-cell compact="true">
                        <div class="d-flex items-center gap-1">
                            @if($assistant->ownership_type === 'team')
                                <x-ui-badge variant="primary" size="sm">Team</x-ui-badge>
                            @else
                                <x-ui-badge variant="success" size="sm">Privat</x-ui-badge>
                            @endif
                            @if($assistant->is_active)
                                <x-ui-badge variant="success" size="sm">Aktiv</x-ui-badge>
                            @else
                                <x-ui-badge variant="secondary" size="sm">Inaktiv</x-ui-badge>
                            @endif
                        </div>
                    </x-ui-table-cell>
                    <x-ui-table-cell compact="true" align="right">
                        <x-ui-button 
                            size="sm" 
                            variant="secondary" 
                            href="{{ route('ai-assistant.assistants.show', ['assistant' => $assistant->id]) }}" 
                            wire:navigate
                        >
                            Bearbeiten
                        </x-ui-button>
                    </x-ui-table-cell>
                </x-ui-table-row>
            @empty
                <x-ui-table-row compact="true">
                    <x-ui-table-cell compact="true" colspan="5">
                        <div class="text-center py-8">
                            <x-heroicon-o-cpu-chip class="w-12 h-12 mx-auto mb-4 text-muted" />
                            <h3 class="text-lg font-medium mb-2">Keine Assistants gefunden</h3>
                            <p class="text-muted mb-4">Erstellen Sie Ihren ersten KI-Assistenten</p>
                            <x-ui-button variant="primary" wire:click="openCreateModal">
                                Neuer Assistant
                            </x-ui-button>
                        </div>
                    </x-ui-table-cell>
                </x-ui-table-row>
            @endforelse
        </x-ui-table-body>
    </x-ui-table>

    <!-- Pagination -->
    @if($assistants->hasPages())
        <div class="mt-6">
            {{ $assistants->links() }}
        </div>
    @endif

    <!-- Create Assistant Modal -->
    <x-ui-modal
        wire:model="modalShow"
        size="lg"
    >
        <x-slot name="header">
            Neuen Assistant erstellen
        </x-slot>

        <div class="space-y-4">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                <div class="d-flex items-center gap-2 mb-2">
                    @svg('heroicon-o-information-circle', 'w-5 h-5 text-blue-600')
                    <h4 class="font-medium text-blue-900">Hinweis</h4>
                </div>
                <p class="text-blue-700 text-sm">Erstellen Sie einen KI-Assistenten mit spezifischen Anweisungen und Tools. Der Assistant kann später in verschiedenen Kontexten verwendet werden.</p>
            </div>

            <form wire:submit.prevent="createAssistant" class="space-y-4">
                <x-ui-input-text
                    name="name"
                    label="Name"
                    wire:model.live="name"
                    required
                    placeholder="z.B. Projekt-Intake Assistant"
                />

                <x-ui-input-text
                    name="description"
                    label="Beschreibung"
                    wire:model.live="description"
                    placeholder="Kurze Beschreibung des Assistants (max. 512 Zeichen)"
                />

                <x-ui-input-textarea
                    name="instructions"
                    label="Anweisungen"
                    wire:model.live="instructions"
                    required
                    placeholder="Beschreiben Sie die Rolle und das Verhalten des Assistants..."
                    rows="6"
                />

                <div class="grid grid-cols-2 gap-4">
                    <x-ui-input-select
                        name="model"
                        label="OpenAI Model"
                        :options="$models"
                        optionValue="model_id"
                        optionLabel="display_name"
                        wire:model.live="model"
                        required
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
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <x-ui-input-select
                        name="response_format"
                        label="Response Format"
                        :options="collect([
                            ['id' => 'auto', 'name' => 'Auto'],
                            ['id' => 'json_object', 'name' => 'JSON Object']
                        ])"
                        optionValue="id"
                        optionLabel="name"
                        wire:model.live="response_format"
                    />

                    @if($supportsReasoningEffort)
                        <x-ui-input-select
                            name="reasoning_effort"
                            label="Reasoning Effort"
                            :options="collect([
                                ['id' => 'minimal', 'name' => 'Minimal'],
                                ['id' => 'low', 'name' => 'Low'],
                                ['id' => 'medium', 'name' => 'Medium'],
                                ['id' => 'high', 'name' => 'High']
                            ])"
                            optionValue="id"
                            optionLabel="name"
                            wire:model.live="reasoning_effort"
                        />
                    @else
                        <div class="text-sm text-muted d-flex items-center">
                            <span>Reasoning Effort nicht verfügbar für dieses Model</span>
                        </div>
                    @endif

                    <x-ui-input-text
                        name="temperature"
                        label="Temperature"
                        type="number"
                        step="0.1"
                        min="0"
                        max="2"
                        wire:model.live="temperature"
                        placeholder="1.0"
                    />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <x-ui-input-text
                        name="top_p"
                        label="Top P"
                        type="number"
                        step="0.1"
                        min="0"
                        max="1"
                        wire:model.live="top_p"
                        placeholder="1.0"
                    />

                    <x-ui-input-textarea
                        name="metadata"
                        label="Metadaten (JSON)"
                        wire:model.live="metadata"
                        placeholder='{"key": "value"}'
                        rows="3"
                    />
                </div>

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
                <x-ui-button type="button" variant="primary" wire:click="createAssistant">
                    Assistant erstellen
                </x-ui-button>
            </div>
        </x-slot>
    </x-ui-modal>
</div>
