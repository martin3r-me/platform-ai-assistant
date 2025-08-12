<?php

namespace Platform\AiAssistant\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ThreadService
{
    private string $apiKey;
    private string $baseUrl;
    private ?string $organization;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->baseUrl = config('services.openai.base_url', 'https://api.openai.com/v1');
        $this->organization = config('services.openai.organization');
    }

    /**
     * Erstellt einen Thread bei OpenAI
     */
    public function createThread(array $data = []): ?string
    {
        try {
            if (empty($this->apiKey)) {
                throw new \Exception('OpenAI API Key nicht konfiguriert');
            }

            Log::info('Creating OpenAI Thread', [
                'has_messages' => !empty($data['messages']),
                'has_metadata' => !empty($data['metadata']),
                'has_tool_resources' => !empty($data['tool_resources']),
            ]);

            $payload = [];
            
            if (!empty($data['messages'])) {
                $payload['messages'] = $data['messages'];
            }
            
            if (!empty($data['metadata'])) {
                $payload['metadata'] = $data['metadata'];
            }
            
            if (!empty($data['tool_resources'])) {
                $payload['tool_resources'] = $data['tool_resources'];
            }

            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2',
            ];

            if ($this->organization) {
                $headers['OpenAI-Organization'] = $this->organization;
            }

            $response = Http::withHeaders($headers)
                ->post($this->baseUrl . '/threads', $payload);

            if ($response->successful()) {
                return $response->json()['id'];
            } else {
                $errorResponse = $response->json();
                $errorMessage = $errorResponse['error']['message'] ?? 'Unbekannter Fehler';
                
                Log::error('OpenAI Thread Creation Failed', [
                    'status' => $response->status(),
                    'response' => $errorResponse,
                    'payload' => $payload,
                ]);
                
                throw new \Exception('OpenAI API Fehler: ' . $errorMessage);
            }
        } catch (\Exception $e) {
            Log::error('Thread Creation Error', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Löscht einen Thread bei OpenAI
     */
    public function deleteThread(string $openaiThreadId): bool
    {
        try {
            if (empty($this->apiKey)) {
                throw new \Exception('OpenAI API Key nicht konfiguriert');
            }

            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'OpenAI-Beta' => 'assistants=v2',
            ];

            if ($this->organization) {
                $headers['OpenAI-Organization'] = $this->organization;
            }

            $response = Http::withHeaders($headers)
                ->delete($this->baseUrl . '/threads/' . $openaiThreadId);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Thread Deletion Error', [
                'error' => $e->getMessage(),
                'thread_id' => $openaiThreadId,
            ]);
            throw $e;
        }
    }

    /**
     * Erstellt eine Message in einem Thread
     */
    public function createMessage(string $openaiThreadId, string $role, string $content, array $fileIds = []): ?string
    {
        try {
            if (empty($this->apiKey)) {
                throw new \Exception('OpenAI API Key nicht konfiguriert');
            }

            Log::info('Creating OpenAI Message', [
                'thread_id' => $openaiThreadId,
                'role' => $role,
                'content_length' => strlen($content),
                'has_files' => !empty($fileIds),
            ]);

            $payload = [
                'role' => $role,
                'content' => $content,
            ];

            if (!empty($fileIds)) {
                $payload['file_ids'] = $fileIds;
            }

            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2',
            ];

            if ($this->organization) {
                $headers['OpenAI-Organization'] = $this->organization;
            }

            $response = Http::withHeaders($headers)
                ->post($this->baseUrl . '/threads/' . $openaiThreadId . '/messages', $payload);

            if ($response->successful()) {
                return $response->json()['id'];
            } else {
                $errorResponse = $response->json();
                $errorMessage = $errorResponse['error']['message'] ?? 'Unbekannter Fehler';
                
                Log::error('OpenAI Message Creation Failed', [
                    'status' => $response->status(),
                    'response' => $errorResponse,
                    'payload' => $payload,
                ]);
                
                throw new \Exception('OpenAI API Fehler: ' . $errorMessage);
            }
        } catch (\Exception $e) {
            Log::error('Message Creation Error', [
                'error' => $e->getMessage(),
                'thread_id' => $openaiThreadId,
            ]);
            throw $e;
        }
    }

    /**
     * Holt einen Thread von OpenAI
     */
    public function getThread(string $openaiThreadId): ?array
    {
        try {
            if (empty($this->apiKey)) {
                throw new \Exception('OpenAI API Key nicht konfiguriert');
            }

            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'OpenAI-Beta' => 'assistants=v2',
            ];

            if ($this->organization) {
                $headers['OpenAI-Organization'] = $this->organization;
            }

            $response = Http::withHeaders($headers)
                ->get($this->baseUrl . '/threads/' . $openaiThreadId);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('OpenAI Thread Fetch Failed', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Thread Fetch Error', [
                'error' => $e->getMessage(),
                'thread_id' => $openaiThreadId,
            ]);
            return null;
        }
    }
}
