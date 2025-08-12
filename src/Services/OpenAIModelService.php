<?php

namespace Platform\AiAssistant\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\AiAssistant\Models\AiAssistantModel;

class OpenAIModelService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://api.openai.com/v1';

    public function __construct()
    {
        $this->apiKey = config('ai-assistant.openai.api_key');
        $this->baseUrl = config('ai-assistant.openai.base_url', 'https://api.openai.com/v1');
    }

    /**
     * Holt alle verfügbaren Modelle von der OpenAI API
     */
    public function fetchModelsFromAPI(): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->get($this->baseUrl . '/models');

            if ($response->successful()) {
                return $response->json('data', []);
            }

            Log::error('OpenAI API Error: ' . $response->body());
            return [];
        } catch (\Exception $e) {
            Log::error('OpenAI API Exception: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Synchronisiert Modelle mit der Datenbank
     */
    public function syncModels(?int $teamId = null, ?int $userId = null): array
    {
        $apiModels = $this->fetchModelsFromAPI();
        $syncedModels = [];
        $errors = [];

        foreach ($apiModels as $apiModel) {
            try {
                $modelId = $apiModel['id'];
                $modelName = $this->extractModelName($modelId);
                $category = $this->categorizeModel($modelId);

                // Prüfe ob Model bereits existiert (global oder team-spezifisch)
                $existingModel = AiAssistantModel::where('model_id', $modelId)
                    ->where(function($query) use ($teamId) {
                        if ($teamId) {
                            $query->where('team_id', $teamId)->orWhereNull('team_id');
                        } else {
                            $query->whereNull('team_id');
                        }
                    })
                    ->first();

                if ($existingModel) {
                    // Update existierendes Model
                    $existingModel->update([
                        'name' => $modelName,
                        'description' => $apiModel['description'] ?? null,
                        'category' => $category,
                        'api_metadata' => $apiModel,
                        'last_api_check' => now(),
                    ]);
                    $syncedModels[] = $existingModel;
                } else {
                    // Erstelle neues Model (global oder team-spezifisch)
                    $newModel = new AiAssistantModel();
                    $newModel->model_id = (string) $modelId;
                    $newModel->name = (string) $modelName;
                    $newModel->description = $apiModel['description'] ?? null;
                    $newModel->category = (string) $category;
                    $newModel->is_active = true;
                    $newModel->is_deprecated = false;
                    $newModel->api_metadata = $apiModel;
                    $newModel->last_api_check = now();
                    $newModel->team_id = $teamId; // null für globale Modelle
                    $newModel->created_by_user_id = $userId; // null für globale Modelle
                    $newModel->save();
                    $syncedModels[] = $newModel;
                }
            } catch (\Exception $e) {
                $errors[] = "Fehler beim Synchronisieren von Model {$modelId}: " . $e->getMessage();
                Log::error("Model Sync Error for {$modelId}: " . $e->getMessage());
            }
        }

        // Markiere nicht mehr verfügbare Modelle als deprecated
        $this->markDeprecatedModels($apiModels, $teamId);

        return [
            'synced' => $syncedModels,
            'errors' => $errors,
        ];
    }

    /**
     * Markiert Modelle als deprecated, die nicht mehr in der API verfügbar sind
     */
    protected function markDeprecatedModels(array $apiModels, ?int $teamId = null): void
    {
        $availableModelIds = collect($apiModels)->pluck('id')->toArray();
        
        $query = AiAssistantModel::where('is_active', true)
            ->whereNotIn('model_id', $availableModelIds);
            
        if ($teamId) {
            $query->where('team_id', $teamId);
        } else {
            $query->whereNull('team_id'); // Nur globale Modelle
        }
        
        $deprecatedModels = $query->get();

        foreach ($deprecatedModels as $model) {
            $model->markAsDeprecated();
            Log::info("Model {$model->model_id} marked as deprecated");
        }
    }

    /**
     * Extrahiert einen benutzerfreundlichen Namen aus der Model ID
     */
    protected function extractModelName(string $modelId): string
    {
        $nameMap = [
            'gpt-5' => 'GPT-5',
            'gpt-5-mini' => 'GPT-5 Mini',
            'gpt-5-nano' => 'GPT-5 Nano',
            'gpt-4.1' => 'GPT-4.1',
            'gpt-4.1-mini' => 'GPT-4.1 Mini',
            'gpt-4.1-nano' => 'GPT-4.1 Nano',
            'gpt-4o' => 'GPT-4o',
            'gpt-4o-mini' => 'GPT-4o Mini',
            'o3' => 'O3',
            'o3-mini' => 'O3 Mini',
            'o4-mini' => 'O4 Mini',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
        ];

        return $nameMap[$modelId] ?? ucfirst(str_replace(['-', '_'], ' ', $modelId));
    }

    /**
     * Kategorisiert Modelle
     */
    protected function categorizeModel(string $modelId): string
    {
        if (str_starts_with($modelId, 'gpt-5')) {
            return 'GPT-5';
        }
        if (str_starts_with($modelId, 'gpt-4.1')) {
            return 'GPT-4.1';
        }
        if (str_starts_with($modelId, 'gpt-4o')) {
            return 'GPT-4o';
        }
        if (str_starts_with($modelId, 'o')) {
            return 'O-Series';
        }
        if (str_starts_with($modelId, 'gpt-4')) {
            return 'GPT-4';
        }
        if (str_starts_with($modelId, 'gpt-3.5')) {
            return 'GPT-3.5';
        }

        return 'Other';
    }

    /**
     * Holt verfügbare Modelle (globale + team-spezifische)
     */
    public function getAvailableModels(?int $teamId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = AiAssistantModel::available();
        
        if ($teamId) {
            $query->where(function($q) use ($teamId) {
                $q->where('team_id', $teamId)
                  ->orWhereNull('team_id'); // Globale Modelle
            });
        } else {
            $query->whereNull('team_id'); // Nur globale Modelle
        }
        
        return $query->orderBy('category')
                    ->orderBy('name')
                    ->get();
    }
}
