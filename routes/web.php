<?php

use Illuminate\Support\Facades\Route;
use Platform\AiAssistant\Livewire\Dashboard;
use Platform\AiAssistant\Livewire\Assistant\Index as AssistantIndex;
use Platform\AiAssistant\Livewire\Assistant\Show as AssistantShow;
use Platform\AiAssistant\Livewire\Thread\Index as ThreadIndex;
use Platform\AiAssistant\Livewire\Thread\Show as ThreadShow;


Route::get('/', Dashboard::class)->name('ai-assistant.dashboard');

Route::get('/assistants', AssistantIndex::class)->name('ai-assistant.assistants.index');
Route::get('/assistants/{assistant}', AssistantShow::class)->name('ai-assistant.assistants.show');

Route::get('/threads', ThreadIndex::class)->name('ai-assistant.threads.index');
Route::get('/threads/{thread}', ThreadShow::class)->name('ai-assistant.threads.show');

