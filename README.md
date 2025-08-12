# AI Assistant Module

## Installation

### 1. Environment Variables

Fügen Sie folgende Variablen zu Ihrer `.env` Datei hinzu:

```env
# AI Assistant Module Configuration
AI_ASSISTANT_MODE=subdomain

# OpenAI API Configuration
OPENAI_API_KEY=your_openai_api_key_here
OPENAI_ORGANIZATION=your_openai_organization_id_here
OPENAI_BASE_URL=https://api.openai.com/v1

# Default Models
OPENAI_DEFAULT_MODEL=gpt-5-mini
OPENAI_FALLBACK_MODEL=gpt-4o-mini

# Model Sync Settings
OPENAI_SYNC_MODELS_ON_BOOT=false
OPENAI_SYNC_MODELS_SCHEDULE=daily

# Rate Limiting
OPENAI_MAX_REQUESTS_PER_MINUTE=60
OPENAI_MAX_TOKENS_PER_REQUEST=4000

# AI Assistant Module Settings
AI_ASSISTANT_MAX_MESSAGES_PER_THREAD=1000
AI_ASSISTANT_AUTO_ARCHIVE_AFTER_DAYS=30
```

### 2. Migrationen ausführen

```bash
php artisan migrate --path=platform/modules/ai-assistant/database/migrations
```

### 3. Modelle synchronisieren

#### Mit Artisan Command (Empfohlen):

```bash
# Synchronisiere globale Modelle (für alle Teams verfügbar)
php artisan ai-assistant:sync-models

# Dry Run (zeigt was synchronisiert würde, ohne es zu tun)
php artisan ai-assistant:sync-models --dry-run
```

#### Mit Tinker (Alternative):

```bash
php artisan tinker
```

```php
$modelService = app(\Platform\AiAssistant\Services\OpenAIModelService::class);
$result = $modelService->syncModels(auth()->user()->current_team_id, auth()->id());
dd($result);
```

## Features

- ✅ Dynamische OpenAI Modelle via API
- ✅ Automatische Deprecation von nicht verfügbaren Modellen
- ✅ Multi-Tenant Support
- ✅ Assistant & Thread Management
- ✅ Integration mit anderen Modulen (Hatch, CRM, etc.)

## Verwendung

### Assistant erstellen

```php
use Platform\AiAssistant\Models\AiAssistantAssistant;

$assistant = AiAssistantAssistant::create([
    'name' => 'Projekt-Intake Assistant',
    'instructions' => 'Du bist ein hilfreicher Assistent für Projekt-Intakes...',
    'model' => 'gpt-5-mini',
    'ownership_type' => 'team',
    'team_id' => auth()->user()->current_team_id,
    'created_by_user_id' => auth()->id(),
]);
```

### Thread erstellen

```php
use Platform\AiAssistant\Models\AiAssistantThread;

$thread = AiAssistantThread::create([
    'assistant_id' => $assistant->id,
    'context_type' => 'Platform\\Hatch\\Models\\HatchProjectIntake',
    'context_id' => $projectIntake->id,
    'status' => 'active',
    'team_id' => auth()->user()->current_team_id,
    'created_by_user_id' => auth()->id(),
]);
```
