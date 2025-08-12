<?php

namespace Platform\AiAssistant\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\AiAssistant\Models\AiAssistantAssistant;
use Platform\AiAssistant\Models\AiAssistantModel;

class AssistantService
{
    protected $apiKey;
    protected $baseUrl;
    protected $organization;

    public function __construct()
    {
        $this->apiKey = config('ai-assistant.openai.api_key');
        $this->baseUrl = config('ai-assistant.openai.base_url');
        $this->organization = config('ai-assistant.openai.organization');
    }

    /**
     * Erstellt einen Assistant bei OpenAI
     */
    public function createAssistant(array $data): ?string
    {
        try {
            // Debug: Prüfe API Key
            if (empty($this->apiKey)) {
                throw new \Exception('OpenAI API Key nicht konfiguriert');
            }
            
            $aiModel = AiAssistantModel::find($data['ai_model_id']);
            if (!$aiModel) {
                throw new \Exception('AI Model nicht gefunden');
            }
            
            Log::info('Creating OpenAI Assistant', [
                'model_id' => $aiModel->model_id,
                'name' => $data['name'],
                'has_instructions' => !empty($data['instructions']),
            ]);

            $payload = [
                'name' => $data['name'],
                'model' => $aiModel->model_id,
            ];
            
            // Optionale Felder hinzufügen
            if (!empty($data['description'])) {
                $payload['description'] = $data['description'];
            }
            if (!empty($data['instructions'])) {
                $payload['instructions'] = $data['instructions'];
            }
            if (!empty($data['tools'])) {
                $payload['tools'] = $data['tools'];
            }
            if (!empty($data['tool_resources'])) {
                $payload['tool_resources'] = $data['tool_resources'];
            }
            if (!empty($data['metadata'])) {
                $payload['metadata'] = $data['metadata'];
            }
            if (!empty($data['response_format'])) {
                $payload['response_format'] = $data['response_format'];
            }
            if (isset($data['temperature'])) {
                $payload['temperature'] = (float) $data['temperature'];
            }
            if (isset($data['top_p'])) {
                $payload['top_p'] = (float) $data['top_p'];
            }
            // reasoning_effort nur für unterstützte Modelle
            if (!empty($data['reasoning_effort']) && $this->isReasoningEffortSupported($aiModel->model_id)) {
                $payload['reasoning_effort'] = $data['reasoning_effort'];
            }

            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2',
            ];
            
            if ($this->organization) {
                $headers['OpenAI-Organization'] = $this->organization;
            }
            
            $response = Http::withHeaders($headers)->post($this->baseUrl . '/assistants', $payload);

            if ($response->successful()) {
                $result = $response->json();
                return $result['id']; // OpenAI Assistant ID
            } else {
                $errorResponse = $response->json();
                $errorMessage = $errorResponse['error']['message'] ?? 'Unbekannter Fehler';
                
                Log::error('OpenAI Assistant Creation Failed', [
                    'status' => $response->status(),
                    'response' => $errorResponse,
                    'payload' => $payload,
                ]);
                
                throw new \Exception('OpenAI API Fehler: ' . $errorMessage);
            }
        } catch (\Exception $e) {
            Log::error('Assistant Creation Error', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Löscht einen Assistant bei OpenAI
     */
    public function deleteAssistant(string $openaiAssistantId): bool
    {
        try {
            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'OpenAI-Beta' => 'assistants=v2',
            ];
            
            if ($this->organization) {
                $headers['OpenAI-Organization'] = $this->organization;
            }
            
            $response = Http::withHeaders($headers)->delete($this->baseUrl . '/assistants/' . $openaiAssistantId);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Assistant Deletion Error', [
                'error' => $e->getMessage(),
                'assistant_id' => $openaiAssistantId,
            ]);
            return false;
        }
    }

    /**
     * Aktualisiert einen Assistant bei OpenAI
     */
    public function updateAssistant(string $openaiAssistantId, array $data): bool
    {
        try {
            $aiModel = null;
            if (isset($data['ai_model_id'])) {
                $aiModel = AiAssistantModel::find($data['ai_model_id']);
            }

            $payload = [];
            
            // Alle möglichen Felder hinzufügen
            if (isset($data['name'])) {
                $payload['name'] = $data['name'];
            }
            if (isset($data['description'])) {
                $payload['description'] = $data['description'];
            }
            if (isset($data['instructions'])) {
                $payload['instructions'] = $data['instructions'];
            }
            if ($aiModel) {
                $payload['model'] = $aiModel->model_id;
            }
            if (isset($data['tools'])) {
                $payload['tools'] = $data['tools'];
            }
            if (isset($data['tool_resources'])) {
                $payload['tool_resources'] = $data['tool_resources'];
            }
            if (isset($data['metadata'])) {
                $payload['metadata'] = $data['metadata'];
            }
            if (isset($data['response_format'])) {
                $payload['response_format'] = $data['response_format'];
            }
            if (isset($data['temperature'])) {
                $payload['temperature'] = (float) $data['temperature'];
            }
            if (isset($data['top_p'])) {
                $payload['top_p'] = (float) $data['top_p'];
            }
            // reasoning_effort nur für unterstützte Modelle
            if (isset($data['reasoning_effort']) && $aiModel && $this->isReasoningEffortSupported($aiModel->model_id)) {
                $payload['reasoning_effort'] = $data['reasoning_effort'];
            }

            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2',
            ];
            
            if ($this->organization) {
                $headers['OpenAI-Organization'] = $this->organization;
            }
            
            $response = Http::withHeaders($headers)->patch($this->baseUrl . '/assistants/' . $openaiAssistantId, $payload);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Assistant Update Error', [
                'error' => $e->getMessage(),
                'assistant_id' => $openaiAssistantId,
                'data' => $data,
            ]);
            return false;
        }
    }

    /**
     * Prüft ob reasoning_effort für das Model unterstützt wird
     */
    private function isReasoningEffortSupported(string $modelId): bool
    {
        $supportedModels = [
            'gpt-4o',
            'gpt-4o-mini',
            'gpt-4o-preview',
            'gpt-4o-2024-07-18',
            'gpt-4o-mini-2024-07-18',
            'gpt-4o-preview-2024-07-18',
            'gpt-4o-2024-08-01',
            'gpt-4o-mini-2024-08-01',
            'gpt-4o-preview-2024-08-01',
            'gpt-4o-2024-09-01',
            'gpt-4o-mini-2024-09-01',
            'gpt-4o-preview-2024-09-01',
            'gpt-4o-2024-10-01',
            'gpt-4o-mini-2024-10-01',
            'gpt-4o-preview-2024-10-01',
            'gpt-4o-2024-11-01',
            'gpt-4o-mini-2024-11-01',
            'gpt-4o-preview-2024-11-01',
            'gpt-4o-2024-12-01',
            'gpt-4o-mini-2024-12-01',
            'gpt-4o-preview-2024-12-01',
            'gpt-4o-2025-01-01',
            'gpt-4o-mini-2025-01-01',
            'gpt-4o-preview-2025-01-01',
            'gpt-4o-2025-02-01',
            'gpt-4o-mini-2025-02-01',
            'gpt-4o-preview-2025-02-01',
            'gpt-4o-2025-03-01',
            'gpt-4o-mini-2025-03-01',
            'gpt-4o-preview-2025-03-01',
            'gpt-4o-2025-04-01',
            'gpt-4o-mini-2025-04-01',
            'gpt-4o-preview-2025-04-01',
            'gpt-4o-2025-05-01',
            'gpt-4o-mini-2025-05-01',
            'gpt-4o-preview-2025-05-01',
            'gpt-4o-2025-06-01',
            'gpt-4o-mini-2025-06-01',
            'gpt-4o-preview-2025-06-01',
            'gpt-4o-2025-07-01',
            'gpt-4o-mini-2025-07-01',
            'gpt-4o-preview-2025-07-01',
            'gpt-4o-2025-08-01',
            'gpt-4o-mini-2025-08-01',
            'gpt-4o-preview-2025-08-01',
            'gpt-4o-2025-09-01',
            'gpt-4o-mini-2025-09-01',
            'gpt-4o-preview-2025-09-01',
            'gpt-4o-2025-10-01',
            'gpt-4o-mini-2025-10-01',
            'gpt-4o-preview-2025-10-01',
            'gpt-4o-2025-11-01',
            'gpt-4o-mini-2025-11-01',
            'gpt-4o-preview-2025-11-01',
            'gpt-4o-2025-12-01',
            'gpt-4o-mini-2025-12-01',
            'gpt-4o-preview-2025-12-01',
        ];

        return in_array($modelId, $supportedModels);
    }
}
