<div class="h-full overflow-y-auto p-6">
    <!-- Header mit Datum und Perspektive-Toggle -->
    <div class="mb-6">
        <div class="d-flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">AI Assistant Dashboard</h1>
                <p class="text-gray-600">{{ now()->format('l') }}, {{ now()->format('d.m.Y') }}</p>
            </div>
            <div class="d-flex items-center gap-4">
                <!-- Perspektive-Toggle -->
                <div class="d-flex bg-gray-100 rounded-lg p-1">
                    <button 
                        wire:click="$set('perspective', 'personal')"
                        class="px-4 py-2 rounded-md text-sm font-medium transition"
                        :class="'{{ $perspective }}' === 'personal' 
                            ? 'bg-success text-on-success shadow-sm' 
                            : 'text-gray-600 hover:text-gray-900'"
                    >
                        <div class="d-flex items-center gap-2">
                            @svg('heroicon-o-user', 'w-4 h-4')
                            <span>Persönlich</span>
                        </div>
                    </button>
                    <button 
                        wire:click="$set('perspective', 'team')"
                        class="px-4 py-2 rounded-md text-sm font-medium transition"
                        :class="'{{ $perspective }}' === 'team' 
                            ? 'bg-success text-on-success shadow-sm' 
                            : 'text-gray-600 hover:text-gray-900'"
                    >
                        <div class="d-flex items-center gap-2">
                            @svg('heroicon-o-users', 'w-4 h-4')
                            <span>Team</span>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Perspektive-spezifische Statistiken -->
    @if($perspective === 'personal')
        <!-- Persönliche Perspektive -->
        <div class="mb-4">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <div class="d-flex items-center gap-2 mb-2">
                    @svg('heroicon-o-user', 'w-5 h-5 text-blue-600')
                    <h3 class="text-lg font-semibold text-blue-900">Persönliche Übersicht</h3>
                </div>
                <p class="text-blue-700 text-sm">Deine persönlichen AI Assistants und Threads.</p>
            </div>
        </div>
    @else
        <!-- Team-Perspektive -->
        <div class="mb-4">
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="d-flex items-center gap-2 mb-2">
                    @svg('heroicon-o-users', 'w-5 h-5 text-green-600')
                    <h3 class="text-lg font-semibold text-green-900">Team-Übersicht</h3>
                </div>
                <p class="text-green-700 text-sm">Alle AI Assistants und Threads des Teams.</p>
            </div>
        </div>
    @endif

    <!-- Haupt-Statistiken (4x2 Grid) -->
    <div class="grid grid-cols-4 gap-4 mb-8">
        <!-- AI Assistants -->
        <x-ui-dashboard-tile
            title="AI Assistants"
            :count="$this->totalAssistants"
            icon="cpu-chip"
            variant="primary"
            size="lg"
            :href="route('ai-assistant.assistants.index')"
        />
        
        <!-- Threads -->
        <x-ui-dashboard-tile
            title="Threads"
            :count="$this->totalThreads"
            icon="chat-bubble-left-right"
            variant="secondary"
            size="lg"
            :href="route('ai-assistant.threads.index')"
        />
        
        <!-- Nachrichten -->
        <x-ui-dashboard-tile
            title="Nachrichten"
            :count="$this->totalMessages"
            icon="envelope"
            variant="success"
            size="lg"
        />
        
        <!-- Runs -->
        <x-ui-dashboard-tile
            title="Runs"
            :count="$this->totalRuns"
            icon="arrow-path"
            variant="warning"
            size="lg"
        />
    </div>

    <!-- Detaillierte Statistiken (2x3 Grid) -->
    <div class="grid grid-cols-2 gap-6 mb-8">
        <!-- Linke Spalte: Aktivitätsdaten -->
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Aktivitätsdaten</h3>
            
            <div class="grid grid-cols-2 gap-3">
                <x-ui-dashboard-tile
                    title="Aktive Runs"
                    :count="$this->activeRuns"
                    icon="arrow-path"
                    variant="warning"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Abgeschlossene Runs"
                    :count="$this->completedRuns"
                    icon="check-circle"
                    variant="success"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Fehlgeschlagene Runs"
                    :count="$this->failedRuns"
                    icon="exclamation-triangle"
                    variant="danger"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Aktive Threads"
                    :count="$this->activeThreads"
                    icon="chat-bubble-left-right"
                    variant="info"
                    size="sm"
                />
            </div>
        </div>

        <!-- Rechte Spalte: Qualitätsmetriken -->
        <div class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Qualitätsmetriken</h3>
            
            <div class="grid grid-cols-2 gap-3">
                <x-ui-dashboard-tile
                    title="Assistants ohne Model"
                    :count="$this->assistantsWithoutModel"
                    icon="exclamation-triangle"
                    variant="warning"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Threads ohne Assistant"
                    :count="$this->threadsWithoutAssistant"
                    icon="exclamation-triangle"
                    variant="warning"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Archivierte Threads"
                    :count="$this->archivedThreads"
                    icon="archive-box"
                    variant="secondary"
                    size="sm"
                />
                
                <x-ui-dashboard-tile
                    title="Team-Assistants"
                    :count="$this->teamAssistants"
                    icon="users"
                    variant="primary"
                    size="sm"
                />
            </div>
        </div>
    </div>

    <!-- Aktuelle Aktivitäten -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <!-- Neueste AI Assistants -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Neueste AI Assistants</h3>
                <p class="text-sm text-gray-600 mt-1">Die 5 zuletzt erstellten AI Assistants</p>
            </div>
            <div class="p-6">
                @if($this->recentAssistants->count() > 0)
                    <div class="space-y-4">
                        @foreach($this->recentAssistants as $assistant)
                            <div class="d-flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                                <div class="d-flex items-center gap-4">
                                    <div class="w-10 h-10 bg-primary text-on-primary rounded-lg d-flex items-center justify-center">
                                        <x-heroicon-o-cpu-chip class="w-5 h-5"/>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900">{{ $assistant->name }}</h4>
                                        <p class="text-sm text-gray-600">{{ $assistant->created_at->format('d.m.Y H:i') }}</p>
                                    </div>
                                </div>
                                <a href="{{ route('ai-assistant.assistants.show', ['assistant' => $assistant->id]) }}" 
                                   class="inline-flex items-center gap-2 px-3 py-2 bg-primary text-on-primary rounded-md hover:bg-primary-dark transition text-sm"
                                   wire:navigate>
                                    <div class="d-flex items-center gap-2">
                                        @svg('heroicon-o-arrow-right', 'w-4 h-4')
                                        <span>Anzeigen</span>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <x-heroicon-o-cpu-chip class="w-12 h-12 text-gray-400 mx-auto mb-4"/>
                        <h4 class="text-lg font-medium text-gray-900 mb-2">Keine AI Assistants</h4>
                        <p class="text-gray-600">Es wurden noch keine AI Assistants erstellt.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Neueste Threads -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Neueste Threads</h3>
                <p class="text-sm text-gray-600 mt-1">Die 5 zuletzt erstellten Threads</p>
            </div>
            <div class="p-6">
                @if($this->recentThreads->count() > 0)
                    <div class="space-y-4">
                        @foreach($this->recentThreads as $thread)
                            <div class="d-flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                                <div class="d-flex items-center gap-4">
                                    <div class="w-10 h-10 bg-secondary text-on-secondary rounded-lg d-flex items-center justify-center">
                                        <x-heroicon-o-chat-bubble-left-right class="w-5 h-5"/>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-900">{{ $thread->title }}</h4>
                                        <p class="text-sm text-gray-600">{{ $thread->created_at->format('d.m.Y H:i') }}</p>
                                    </div>
                                </div>
                                <a href="{{ route('ai-assistant.threads.show', ['thread' => $thread->id]) }}" 
                                   class="inline-flex items-center gap-2 px-3 py-2 bg-secondary text-on-secondary rounded-md hover:bg-secondary-dark transition text-sm"
                                   wire:navigate>
                                    <div class="d-flex items-center gap-2">
                                        @svg('heroicon-o-arrow-right', 'w-4 h-4')
                                        <span>Anzeigen</span>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <x-heroicon-o-chat-bubble-left-right class="w-12 h-12 text-gray-400 mx-auto mb-4"/>
                        <h4 class="text-lg font-medium text-gray-900 mb-2">Keine Threads</h4>
                        <p class="text-gray-600">Es wurden noch keine Threads erstellt.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Top AI Models -->
    @if($this->topAiModels->count() > 0)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">Top AI Models</h3>
                <p class="text-sm text-gray-600 mt-1">Verwendung der AI Models</p>
            </div>
            <div class="p-6">
                <div class="space-y-4">
                    @foreach($this->topAiModels as $model)
                        <div class="d-flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                            <div class="d-flex items-center gap-4">
                                <div class="w-10 h-10 bg-primary text-on-primary rounded-lg d-flex items-center justify-center">
                                    <x-heroicon-o-cpu-chip class="w-5 h-5"/>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900">{{ $model->name }}</h4>
                                    <p class="text-sm text-gray-600">{{ $model->count }} Verwendungen</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold text-primary">{{ $model->count }}</div>
                                <div class="text-sm text-gray-600">
                                    {{ round(($model->count / $this->totalAssistants) * 100, 1) }}%
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
