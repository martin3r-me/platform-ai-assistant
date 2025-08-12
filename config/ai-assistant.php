<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Assistant Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for the AI Assistant module.
    |
    */

    'routing' => [
        'mode' => env('AI_ASSISTANT_MODE', 'subdomain'),
        'prefix' => 'ai-assistant',
    ],

    'guard' => 'web',

    'navigation' => [
        'icon' => 'heroicon-o-cpu-chip',
        'order' => 40,
        'route' => 'ai-assistant.dashboard',
    ],

    /*
    |--------------------------------------------------------------------------
    | OpenAI Configuration
    |--------------------------------------------------------------------------
    |
    | OpenAI API settings for the AI Assistant module.
    |
    */
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'organization' => env('OPENAI_ORGANIZATION'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        
        // Default model settings
        'default_model' => env('OPENAI_DEFAULT_MODEL', 'gpt-5-mini'),
        'fallback_model' => env('OPENAI_FALLBACK_MODEL', 'gpt-4o-mini'),
        
        // Model sync settings
        'sync_models_on_boot' => env('OPENAI_SYNC_MODELS_ON_BOOT', false),
        'sync_models_schedule' => env('OPENAI_SYNC_MODELS_SCHEDULE', 'daily'),
        
        // Rate limiting
        'max_requests_per_minute' => env('OPENAI_MAX_REQUESTS_PER_MINUTE', 60),
        'max_tokens_per_request' => env('OPENAI_MAX_TOKENS_PER_REQUEST', 4000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Assistant Configuration
    |--------------------------------------------------------------------------
    |
    | Default settings for AI Assistants.
    |
    */
    'assistant' => [
        'max_instructions_length' => 32768,
        'max_name_length' => 255,
        'default_ownership_type' => 'team',
        'allowed_ownership_types' => ['team', 'user'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Thread Configuration
    |--------------------------------------------------------------------------
    |
    | Default settings for AI Threads.
    |
    */
    'thread' => [
        'max_messages_per_thread' => env('AI_ASSISTANT_MAX_MESSAGES_PER_THREAD', 1000),
        'auto_archive_after_days' => env('AI_ASSISTANT_AUTO_ARCHIVE_AFTER_DAYS', 30),
        'default_status' => 'active',
        'allowed_statuses' => ['active', 'archived', 'completed'],
    ],
];
