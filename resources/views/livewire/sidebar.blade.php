{{-- resources/views/vendor/ai-assistant/livewire/sidebar-content.blade.php --}}
<div>
    {{-- Abschnitt: Allgemein --}}
    <div>
        <h4 x-show="!collapsed" class="p-3 text-sm italic text-secondary uppercase">AI Assistant</h4>

        {{-- Dashboard --}}
        <a href="{{ route('ai-assistant.dashboard') }}"
           class="relative d-flex items-center p-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/ai-assistant') && 
               (window.location.pathname.endsWith('/ai-assistant') || 
                window.location.pathname.endsWith('/ai-assistant/') ||
                window.location.pathname.includes('/ai-assistant/dashboard'))
                   ? 'bg-primary text-on-primary shadow-md'
                   : 'text-black hover:bg-primary-10 hover:text-primary hover:shadow-md',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-chart-bar class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Dashboard</span>
        </a>

        {{-- Assistants --}}
        <a href="{{ route('ai-assistant.assistants.index') }}"
           class="relative d-flex items-center p-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/assistants') || 
               window.location.pathname.endsWith('/assistants') ||
               window.location.pathname.endsWith('/assistants/')
                   ? 'bg-primary text-on-primary shadow-md'
                   : 'text-black hover:bg-primary-10 hover:text-primary hover:shadow-md',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-cpu-chip class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Assistants</span>
            @if($assistantsCount > 0)
                <span x-show="!collapsed" class="ml-auto inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                    {{ $assistantsCount }}
                </span>
            @endif
        </a>

        {{-- Threads --}}
        <a href="{{ route('ai-assistant.threads.index') }}"
           class="relative d-flex items-center p-2 my-1 rounded-md font-medium transition"
           :class="[
               window.location.pathname.includes('/threads') || 
               window.location.pathname.endsWith('/threads') ||
               window.location.pathname.endsWith('/threads/')
                   ? 'bg-primary text-on-primary shadow-md'
                   : 'text-black hover:bg-primary-10 hover:text-primary hover:shadow-md',
               collapsed ? 'justify-center' : 'gap-3'
           ]"
           wire:navigate>
            <x-heroicon-o-chat-bubble-left-right class="w-6 h-6 flex-shrink-0"/>
            <span x-show="!collapsed" class="truncate">Threads</span>
            @if($activeThreadsCount > 0)
                <span x-show="!collapsed" class="ml-auto inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                    {{ $activeThreadsCount }}
                </span>
            @endif
        </a>
    </div>

    {{-- Abschnitt: Schnellzugriff --}}
    <div x-show="!collapsed">
        <h4 class="p-3 text-sm italic text-secondary uppercase">Schnellzugriff</h4>

        {{-- Neueste Threads --}}
        @foreach($recentThreads ?? [] as $thread)
            <a href="{{ route('ai-assistant.threads.show', ['thread' => $thread]) }}"
               class="relative d-flex items-center p-2 my-1 rounded-md font-medium transition gap-3"
               :class="[
                   window.location.pathname.includes('/threads/{{ $thread->id }}/') || 
                   window.location.pathname.endsWith('/threads/{{ $thread->id }}') ||
                   (window.location.pathname.split('/').length === 2 && window.location.pathname.endsWith('/{{ $thread->id }}'))
                       ? 'bg-primary text-on-primary shadow-md'
                       : 'text-black hover:bg-primary-10 hover:text-primary hover:shadow-md'
               ]"
               wire:navigate>
                <x-heroicon-o-chat-bubble-left-right class="w-6 h-6 flex-shrink-0"/>
                <span class="truncate">
                    Thread #{{ $thread->id }}
                    @if($thread->assistant)
                        ({{ $thread->assistant->name }})
                    @endif
                </span>
            </a>
        @endforeach
    </div>
</div>
