<?php

namespace Platform\AiAssistant\Services;

use OpenAI\Client;
use OpenAI\Factory;

class OpenAIService
{
    protected Client $client;
    
    public function __construct()
    {
        $this->client = (new Factory())
            ->withApiKey(config('ai-assistant.openai_api_key'))
            ->withOrganization(config('ai-assistant.openai_organization'))
            ->make();
    }
    
    /**
     * Sende eine Nachricht an ChatGPT und erhalte eine Antwort
     */
    public function chat(array $messages, string $model = 'gpt-4o-mini'): array
    {
        try {
            $response = $this->client->chat()->create([
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => 1000,
                'temperature' => 0.7,
            ]);
            
            return [
                'ok' => true,
                'content' => $response->choices[0]->message->content,
                'usage' => $response->usage->toArray(),
            ];
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Erstelle einen neuen Thread für einen Chat
     */
    public function createThread(): array
    {
        try {
            $response = $this->client->threads()->create([]);
            
            return [
                'ok' => true,
                'thread_id' => $response->id,
            ];
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Sende eine Nachricht an einen Thread
     */
    public function sendMessage(string $threadId, string $content): array
    {
        try {
            // Nachricht zum Thread hinzufügen
            $message = $this->client->threads()->messages()->create($threadId, [
                'role' => 'user',
                'content' => $content,
            ]);
            
            // Run starten
            $run = $this->client->threads()->runs()->create($threadId, [
                'assistant_id' => config('ai-assistant.assistant_id'),
            ]);
            
            return [
                'ok' => true,
                'message_id' => $message->id,
                'run_id' => $run->id,
            ];
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Hole die neuesten Nachrichten aus einem Thread
     */
    public function getMessages(string $threadId): array
    {
        try {
            $messages = $this->client->threads()->messages()->list($threadId);
            
            return [
                'ok' => true,
                'messages' => $messages->data,
            ];
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
