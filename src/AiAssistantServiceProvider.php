<?php

namespace Platform\AiAssistant;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class AiAssistantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Commands registrieren
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Platform\AiAssistant\Console\Commands\SyncOpenAIModels::class,
                \Platform\AiAssistant\Console\Commands\PollOpenAIRuns::class,
            ]);
        }
        
        // Services registrieren
        $this->app->singleton(\Platform\AiAssistant\Services\OpenAIService::class);
        $this->app->singleton(\Platform\AiAssistant\Services\AssistantService::class);
        $this->app->singleton(\Platform\AiAssistant\Services\ThreadService::class);
        $this->app->singleton(\Platform\AiAssistant\Services\OpenAIModelService::class);
    }

    public function boot(): void
    {
        // Schritt 1: Config laden
        $this->mergeConfigFrom(__DIR__.'/../config/ai-assistant.php', 'ai-assistant');
        
        // Schritt 2: Existenzprüfung (config jetzt verfügbar)
        if (
            config()->has('ai-assistant.routing') &&
            config()->has('ai-assistant.navigation') &&
            PlatformCore::modulesTableExists()
        ) {
            PlatformCore::registerModule([
                'key'        => 'ai-assistant',
                'title'      => 'AI Assistant',
                'routing'    => config('ai-assistant.routing'),
                'guard'      => config('ai-assistant.guard'),
                'navigation' => config('ai-assistant.navigation'),
            ]);
        }

        // Schritt 3: Wenn Modul registriert, Routes laden
        if (PlatformCore::getModule('ai-assistant')) {
            ModuleRouter::group('ai-assistant', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/guest.php');
            }, requireAuth: false);

            ModuleRouter::group('ai-assistant', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
        }

        // Schritt 4: Migrationen laden
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Schritt 5: Config veröffentlichen
        $this->publishes([
            __DIR__.'/../config/ai-assistant.php' => config_path('ai-assistant.php'),
        ], 'ai-assistant-config');

        // Schritt 6: Views & Livewire
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ai-assistant');
        $this->registerLivewireComponents();
        
        // Schritt 7: Sidebar-Komponente manuell registrieren
        Livewire::component('ai-assistant.sidebar', \Platform\AiAssistant\Livewire\Sidebar::class);
    }

    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\AiAssistant\\Livewire';
        $prefix = 'ai-assistant';

        if (!is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $classPath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = $baseNamespace . '\\' . $classPath;

            if (!class_exists($class)) {
                continue;
            }

            // ai-assistant.assistant.index aus ai-assistant + assistant/index.php
            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix . '.' . $aliasPath;

            Livewire::component($alias, $class);
        }
    }
}
