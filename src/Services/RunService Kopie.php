<?php

namespace Platform\AiAssistant\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\AiAssistant\Models\AiAssistantRun;
use Platform\AiAssistant\Models\AiAssistantMessage;
use Platform\AiAssistant\Models\AiAssistantToolCall;

class RunService
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
     * Erstellt einen Run bei OpenAI
     */
    public function createRun(string $threadId, string $assistantId, array $data = []): ?string
    {
        try {
            if (empty($this->apiKey)) {
                throw new \Exception('OpenAI API Key nicht konfiguriert');
            }

            Log::info('Creating OpenAI Run', [
                'thread_id' => $threadId,
                'assistant_id' => $assistantId,
                'has_instructions' => !empty($data['instructions']),
                'has_tools' => !empty($data['tools']),
            ]);

            $payload = [
                'assistant_id' => $assistantId,
            ];

            if (!empty($data['instructions'])) {
                $payload['instructions'] = $data['instructions'];
            }

            if (!empty($data['tools'])) {
                $payload['tools'] = $data['tools'];
            }

            if (!empty($data['metadata'])) {
                $payload['metadata'] = $data['metadata'];
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
                ->post($this->baseUrl . '/threads/' . $threadId . '/runs', $payload);

            if ($response->successful()) {
                return $response->json()['id'];
            } else {
                $errorResponse = $response->json();
                $errorMessage = $errorResponse['error']['message'] ?? 'Unbekannter Fehler';
                
                Log::error('OpenAI Run Creation Failed', [
                    'status' => $response->status(),
                    'response' => $errorResponse,
                    'payload' => $payload,
                ]);
                
                throw new \Exception('OpenAI API Fehler: ' . $errorMessage);
            }
        } catch (\Exception $e) {
            Log::error('Run Creation Error', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Holt den Status eines Runs von OpenAI
     */
    public function getRunStatus(string $openaiRunId, string $threadId): ?string
    {
        try {
            if (empty($this->apiKey)) {
                throw new \Exception('OpenAI API Key nicht konfiguriert');
            }

            Log::info('Fetching OpenAI Run Status', [
                'run_id' => $openaiRunId,
                'thread_id' => $threadId,
            ]);

            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'OpenAI-Beta' => 'assistants=v2',
            ];

            if ($this->organization) {
                $headers['OpenAI-Organization'] = $this->organization;
            }

            $url = $this->baseUrl . '/threads/' . $threadId . '/runs/' . $openaiRunId;
            Log::info('OpenAI API Request', ['url' => $url]);

            $response = Http::withHeaders($headers)
                ->get($url);

            Log::info('OpenAI API Response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
            ]);

            if ($response->successful()) {
                $runData = $response->json();
                Log::info('OpenAI Run Status Response', [
                    'run_id' => $openaiRunId,
                    'status' => $runData['status'] ?? 'unknown',
                    'data' => $runData,
                ]);
                return $runData['status'];
            } else {
                Log::error('OpenAI Run Status Fetch Failed', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                    'run_id' => $openaiRunId,
                    'thread_id' => $threadId,
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Run Status Fetch Error', [
                'error' => $e->getMessage(),
                'run_id' => $openaiRunId,
                'thread_id' => $threadId,
            ]);
            return null;
        }
    }

    /**
     * Verarbeitet einen abgeschlossenen Run und holt die Messages
     */
    public function processCompletedRun(AiAssistantRun $run): void
    {
        try {
            // Prüfen ob Run requires_action (Function Call)
            if ($run->status === 'requires_action') {
                $this->processRequiresActionRun($run);
                // requires_action Runs NICHT archivieren - sie werden später archiviert
                return;
            }
            
            // Messages von OpenAI holen
            $messages = $this->getThreadMessages($run->thread->openai_thread_id);
            
            foreach ($messages as $messageData) {
                // Prüfen ob Message bereits existiert
                $existingMessage = AiAssistantMessage::where('openai_message_id', $messageData['id'])->first();
                
                if (!$existingMessage) {
                    // Prüfen ob Function Call vorhanden ist
                    $functionCall = $this->extractFunctionCall($messageData);
                    
                    // Neue Message erstellen
                    AiAssistantMessage::create([
                        'openai_message_id' => $messageData['id'],
                        'thread_id' => $run->thread_id,
                        'run_id' => $run->id,
                        'role' => $messageData['role'],
                        'content' => $messageData['content'][0]['text']['value'] ?? '',
                        'file_ids' => $messageData['file_ids'] ?? null,
                        'metadata' => $messageData['metadata'] ?? null,
                        'team_id' => $run->team_id,
                        'created_by_user_id' => $run->created_by_user_id,
                    ]);
                    
                    // Function Call verarbeiten
                    if ($functionCall) {
                        $this->processFunctionCall($run, $functionCall);
                    }
                }
            }

            // Run als archiviert markieren (nur für completed Runs, nicht requires_action)
            $run->update(['status' => 'archived']);

            Log::info('Run completed and processed', [
                'run_id' => $run->openai_run_id,
                'messages_count' => count($messages),
            ]);

        } catch (\Exception $e) {
            Log::error('Run Processing Error', [
                'error' => $e->getMessage(),
                'run_id' => $run->openai_run_id,
            ]);
            throw $e;
        }
    }
    
    /**
     * Verarbeitet einen requires_action Run (Function Call)
     */
    private function processRequiresActionRun(AiAssistantRun $run): void
    {
        try {
            \Log::channel('project_intake')->info('processRequiresActionRun started', [
                'run_id' => $run->openai_run_id,
                'thread_id' => $run->thread->openai_thread_id,
            ]);
            
            // Run Details von OpenAI holen
            $runDetails = $this->getRunDetails($run->openai_run_id, $run->thread->openai_thread_id);
            
            \Log::channel('project_intake')->info('Run Details for requires_action', [
                'run_id' => $run->openai_run_id,
                'run_details' => $runDetails,
            ]);
            
            if (!$runDetails) {
                Log::error('No run details found', ['run_id' => $run->openai_run_id]);
                return;
            }
            
            \Log::channel('project_intake')->info('Run details retrieved successfully', [
                'run_id' => $run->openai_run_id,
                'has_required_action' => !empty($runDetails['required_action']),
            ]);
            
            if (empty($runDetails['required_action'])) {
                Log::error('No required_action in run details', ['run_id' => $run->openai_run_id]);
                return;
            }
            
            if (empty($runDetails['required_action']['submit_tool_outputs']['tool_calls'])) {
                Log::error('No tool_calls in required_action', [
                    'run_id' => $run->openai_run_id,
                    'required_action' => $runDetails['required_action'],
                ]);
                return;
            }
            
            \Log::channel('project_intake')->info('Tool calls found in required_action', [
                'run_id' => $run->openai_run_id,
                'tool_calls_count' => count($runDetails['required_action']['submit_tool_outputs']['tool_calls']),
            ]);
            
            \Log::channel('project_intake')->info('Starting foreach loop', [
                'tool_calls_count' => count($runDetails['required_action']['submit_tool_outputs']['tool_calls']),
                'run_id' => $run->openai_run_id,
            ]);
            
            foreach ($runDetails['required_action']['submit_tool_outputs']['tool_calls'] as $toolCall) {
                \Log::channel('project_intake')->info('Processing tool call', [
                    'tool_call' => $toolCall,
                    'run_id' => $run->openai_run_id,
                ]);
                
                \Log::channel('project_intake')->info('Tool call type check', [
                    'tool_call_type' => $toolCall['type'] ?? 'unknown',
                    'run_id' => $run->openai_run_id,
                ]);
                
                if ($toolCall['type'] === 'function') {
                    $functionCall = [
                        'id' => $toolCall['id'],
                        'name' => $toolCall['function']['name'],
                        'arguments' => json_decode($toolCall['function']['arguments'], true),
                    ];
                    
                    \Log::channel('project_intake')->info('Function call extracted', [
                        'function_call' => $functionCall,
                        'run_id' => $run->openai_run_id,
                    ]);
                    
                    $this->processFunctionCall($run, $functionCall);
                } else {
                    \Log::channel('project_intake')->warning('Tool call is not a function', [
                        'tool_call_type' => $toolCall['type'],
                        'run_id' => $run->openai_run_id,
                    ]);
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Requires Action Run Processing Error', [
                'error' => $e->getMessage(),
                'run_id' => $run->openai_run_id,
            ]);
        }
    }
    
    /**
     * Holt Run Details von OpenAI
     */
    private function getRunDetails(string $openaiRunId, string $threadId): ?array
    {
        try {
            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'OpenAI-Beta' => 'assistants=v2',
            ];

            if ($this->organization) {
                $headers['OpenAI-Organization'] = $this->organization;
            }

            $response = Http::withHeaders($headers)
                ->get($this->baseUrl . '/threads/' . $threadId . '/runs/' . $openaiRunId);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('OpenAI Run Details Fetch Failed', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Run Details Fetch Error', [
                'error' => $e->getMessage(),
                'run_id' => $openaiRunId,
            ]);
            return null;
        }
    }
    
    /**
     * Function Call aus Message extrahieren
     */
    private function extractFunctionCall(array $messageData): ?array
    {
        if ($messageData['role'] !== 'assistant') {
            return null;
        }
        
        foreach ($messageData['content'] ?? [] as $content) {
            if ($content['type'] === 'function_call') {
                return [
                    'id' => $content['function_call']['id'] ?? '',
                    'name' => $content['function_call']['name'] ?? '',
                    'arguments' => json_decode($content['function_call']['arguments'] ?? '{}', true),
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Function Call verarbeiten
     */
    private function processFunctionCall(AiAssistantRun $run, array $functionCall): void
    {
        \Log::channel('project_intake')->info('processFunctionCall started', [
            'function_name' => $functionCall['name'],
            'run_id' => $run->openai_run_id,
        ]);
        
        if ($functionCall['name'] === 'complete_block') {
            // Prüfen ob dieser Function Call bereits verarbeitet wurde
            $existingToolCall = \Platform\AiAssistant\Models\AiAssistantToolCall::where('openai_tool_call_id', $functionCall['id'])
                ->where('run_id', $run->id)
                ->first();
                
            if ($existingToolCall) {
                \Log::channel('project_intake')->info('Function call already processed', [
                    'tool_call_id' => $existingToolCall->id,
                    'run_id' => $run->openai_run_id,
                ]);
                return;
            }
            
            \Log::channel('project_intake')->info('Block completed via function call', [
                'run_id' => $run->openai_run_id,
                'arguments' => $functionCall['arguments'],
            ]);
            
            \Log::channel('project_intake')->info('Creating tool call record', [
                'function_name' => $functionCall['name'],
                'run_id' => $run->openai_run_id,
            ]);
            
            // Tool Call in der tool_calls Tabelle speichern
            $toolCall = AiAssistantToolCall::create([
                'openai_tool_call_id' => $functionCall['id'] ?? 'function_' . time() . '_' . rand(1000, 9999),
                'run_id' => $run->id,
                'thread_id' => $run->thread_id,
                'type' => 'function',
                'function_name' => $functionCall['name'],
                'function_arguments' => $functionCall['arguments'],
                'status' => 'pending',
                'team_id' => $run->team_id,
                'created_by_user_id' => $run->created_by_user_id,
            ]);
            
            \Log::channel('project_intake')->info('Tool call record created', [
                'tool_call_id' => $toolCall->id,
                'run_id' => $run->openai_run_id,
            ]);
            
            // Function Call Response an OpenAI senden
            $this->submitToolOutputs($run, $toolCall);
            
            // Block Completion verarbeiten
            $this->handleBlockCompletion($run, $toolCall);
        }
    }
    
    /**
     * Block Completion verarbeiten
     */
    private function handleBlockCompletion(AiAssistantRun $run, AiAssistantToolCall $toolCall): void
    {
        try {
            \Log::channel('project_intake')->info('handleBlockCompletion started', [
                'run_id' => $run->openai_run_id,
                'tool_call_id' => $toolCall->id,
                'function_arguments' => $toolCall->function_arguments,
            ]);
            
            // Project Intake über polymorphe Beziehung finden
            $projectIntake = $run->thread->context;
            
            // Fallback: Manuell finden falls polymorphe Beziehung nicht funktioniert
            if (!$projectIntake && $run->thread->context_type === 'Platform\\Hatch\\Models\\HatchProjectIntake') {
                $projectIntake = \Platform\Hatch\Models\HatchProjectIntake::find($run->thread->context_id);
            }
            
            if (!$projectIntake || $projectIntake->status !== 'in_progress') {
                \Log::channel('project_intake')->warning('No project intake found for thread', [
                    'thread_id' => $run->thread_id,
                    'context_type' => $run->thread?->context_type ?? 'null',
                    'context_id' => $run->thread?->context_id ?? 'null',
                    'project_intake_found' => $projectIntake ? $projectIntake->id : 'null',
                ]);
                return;
            }
            
            // Aktuellen Block finden
            $currentStep = $projectIntake->intakeSteps()
                ->where('is_completed', false)
                ->orderBy('id')
                ->first();
                
            if (!$currentStep) {
                \Log::channel('project_intake')->warning('No current step found', [
                    'project_intake_id' => $projectIntake->id,
                ]);
                return;
            }
            
            // Step als completed markieren
            $currentStep->update([
                'is_completed' => true,
                'completed_at' => now(),
                'answers' => $toolCall->function_arguments,
            ]);
            
            \Log::channel('project_intake')->info('Step marked as completed', [
                'step_id' => $currentStep->id,
                'template_block_id' => $currentStep->template_block_id,
            ]);
            
            // Prüfen ob es der letzte Block ist
            $remainingSteps = $projectIntake->intakeSteps()
                ->where('is_completed', false)
                ->count();
                
            if ($remainingSteps === 0) {
                // Projektierung abschließen
                $projectIntake->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
                
                \Log::channel('project_intake')->info('Project intake completed', [
                    'project_intake_id' => $projectIntake->id,
                ]);
            } else {
                // Zum nächsten Block wechseln
                $nextStep = $projectIntake->intakeSteps()
                    ->where('is_completed', false)
                    ->orderBy('id')
                    ->first();
                    
                if ($nextStep) {
                    \Log::channel('project_intake')->info('Moving to next step', [
                        'next_step_id' => $nextStep->id,
                        'template_block_id' => $nextStep->template_block_id,
                    ]);
                    
                    // Warten bis kein aktiver Run mehr läuft
                    $this->waitForRunCompletion($run->thread);
                    
                    // Neuen Run für den nächsten Block starten
                    $this->startNextBlockRun($run->thread, $nextStep);
                }
            }
            
        } catch (\Exception $e) {
            \Log::channel('project_intake')->error('Error in handleBlockCompletion', [
                'error' => $e->getMessage(),
                'run_id' => $run->openai_run_id,
            ]);
        }
    }
    
    /**
     * Warten bis alle Runs abgeschlossen sind
     */
    private function waitForRunCompletion($thread): void
    {
        $maxWaitTime = 30; // Maximal 30 Sekunden warten
        $waitTime = 0;
        
        while ($waitTime < $maxWaitTime) {
            // Prüfen ob noch ein aktiver Run läuft (alle außer archived)
            $activeRun = $thread->runs()
                ->whereNotIn('status', ['archived', 'function_processed'])
                ->first();
                
            if (!$activeRun) {
                \Log::channel('project_intake')->info('No active runs found, proceeding to next block');
                return;
            }
            
            \Log::channel('project_intake')->info('Waiting for run completion', [
                'run_id' => $activeRun->openai_run_id,
                'status' => $activeRun->status,
                'wait_time' => $waitTime,
            ]);
            
            sleep(1);
            $waitTime++;
        }
        
        \Log::channel('project_intake')->warning('Timeout waiting for run completion', [
            'max_wait_time' => $maxWaitTime,
        ]);
    }
    
    /**
     * Neuen Run für den nächsten Block starten
     */
    private function startNextBlockRun($thread, $nextStep): void
    {
        try {
            // System Message für den neuen Block senden
            $this->sendSystemMessageForBlock($thread, $nextStep);
            
            // Neuen Run erstellen
            $this->createRun($thread->openai_thread_id, $thread->assistant->openai_assistant_id);
            
            \Log::channel('project_intake')->info('Started next block run', [
                'next_step_id' => $nextStep->id,
                'template_block_id' => $nextStep->template_block_id,
            ]);
            
        } catch (\Exception $e) {
            \Log::channel('project_intake')->error('Error starting next block run', [
                'error' => $e->getMessage(),
                'next_step_id' => $nextStep->id,
            ]);
        }
    }
    
    /**
     * Response Format zu Function konvertieren
     */
    private function convertResponseFormatToFunction($blockDefinition)
    {
        if (!$blockDefinition->response_format || !is_array($blockDefinition->response_format)) {
            return null;
        }
        
        $properties = [];
        $required = [];
        
        foreach ($blockDefinition->response_format as $field) {
            $fieldName = $this->generateFieldName($field['description'] ?? 'field');
            $fieldType = $this->mapFieldType($field['type'] ?? 'string');
            
            $properties[$fieldName] = [
                'type' => $fieldType,
                'description' => $field['description'] ?? 'Feld',
            ];
            
            // Prüfen ob Feld required ist
            if (isset($field['validations']) && is_array($field['validations'])) {
                foreach ($field['validations'] as $validation) {
                    if ($validation['type'] === 'required') {
                        $required[] = $fieldName;
                        break;
                    }
                }
            }
        }
        
        return [
            'type' => 'function',
            'function' => [
                'name' => 'complete_block',
                'description' => "Block '{$blockDefinition->name}' abschließen wenn alle erforderlichen Daten gesammelt wurden",
                'parameters' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => $required,
                ]
            ]
        ];
    }
    
    /**
     * Feldname aus Beschreibung generieren
     */
    private function generateFieldName($description)
    {
        // Dynamische Feldnamen aus der Beschreibung generieren
        $description = strtolower($description);
        
        // Erste Wörter als snake_case
        $words = explode(' ', $description);
        $name = implode('_', array_slice($words, 0, 3)); // Max 3 Wörter
        return preg_replace('/[^a-z0-9_]/', '', $name) ?: 'field';
    }
    
    /**
     * Feldtyp für OpenAI Schema mappen
     */
    private function mapFieldType($type)
    {
        $typeMap = [
            'string' => 'string',
            'number' => 'number',
            'boolean' => 'boolean',
            'date' => 'string',
            'email' => 'string',
            'url' => 'string',
            'array' => 'array',
            'object' => 'object',
        ];
        
        return $typeMap[$type] ?? 'string';
    }
    
    /**
     * System Message für spezifischen Block senden
     */
    private function sendSystemMessageForBlock($thread, $step): void
    {
        try {
            // Block Definition laden
            $blockDefinition = $step->blockDefinition;
            if (!$blockDefinition) {
                \Log::channel('project_intake')->error('No block definition found for step', [
                    'step_id' => $step->id,
                ]);
                return;
            }
            
            // Project Intake laden
            $projectIntake = $step->projectIntake;
            if (!$projectIntake) {
                \Log::channel('project_intake')->error('No project intake found for step', [
                    'step_id' => $step->id,
                ]);
                return;
            }
            
            // Template laden
            $template = $projectIntake->projectTemplate;
            if (!$template) {
                \Log::channel('project_intake')->error('No template found for project intake', [
                    'project_intake_id' => $projectIntake->id,
                ]);
                return;
            }
            
            // System Message erstellen
            $systemMessage = "Du bist ein KI-Assistent für die Projektierung.\n\n";
            $systemMessage .= "Template: {$template->name}\n";
            $systemMessage .= "Aktueller Block: {$blockDefinition->name}\n\n";
            
            if ($template->ai_personality) {
                $systemMessage .= "Persönlichkeit: {$template->ai_personality}\n";
            }
            
            if ($template->industry_context) {
                $systemMessage .= "Branche: {$template->industry_context}\n";
            }
            
            $systemMessage .= "\n" . $blockDefinition->ai_prompt;
            
            // Function für diesen Block erstellen
            $function = $this->convertResponseFormatToFunction($blockDefinition);
            
            // Run mit Function erstellen
            $runData = [];
            if ($function) {
                $runData['tools'] = [$function];
            }
            
            // Run bei OpenAI erstellen
            $runId = $this->createRun($thread->openai_thread_id, $thread->assistant->openai_assistant_id, $runData);
            
            // Run lokal speichern
            $thread->runs()->create([
                'openai_run_id' => $runId,
                'assistant_id' => $thread->assistant_id,
                'status' => 'queued',
                'type' => 'block_run',
                'team_id' => $thread->team_id,
                'created_by_user_id' => $thread->created_by_user_id,
            ]);
            
            // Message bei OpenAI erstellen
            $threadService = app(\Platform\AiAssistant\Services\ThreadService::class);
            $openaiMessageId = $threadService->createMessage(
                $thread->openai_thread_id,
                'user',
                $systemMessage
            );
            
            // Message lokal speichern
            $thread->messages()->create([
                'openai_message_id' => $openaiMessageId,
                'role' => 'user',
                'content' => $systemMessage,
                'team_id' => $thread->team_id,
                'created_by_user_id' => $thread->created_by_user_id,
            ]);
            
            \Log::channel('project_intake')->info('System message sent for block', [
                'step_id' => $step->id,
                'template_block_id' => $step->template_block_id,
                'block_name' => $blockDefinition->name,
            ]);
            
        } catch (\Exception $e) {
            \Log::channel('project_intake')->error('Error sending system message for block', [
                'error' => $e->getMessage(),
                'step_id' => $step->id,
            ]);
        }
    }

    /**
     * Tool Outputs an OpenAI senden
     */
    private function submitToolOutputs(AiAssistantRun $run, AiAssistantToolCall $toolCall): void
    {
        try {
            \Log::channel('project_intake')->info('submitToolOutputs started', [
                'run_id' => $run->openai_run_id,
                'tool_call_id' => $toolCall->openai_tool_call_id,
            ]);
            
            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'OpenAI-Beta' => 'assistants=v2',
            ];

            if ($this->organization) {
                $headers['OpenAI-Organization'] = $this->organization;
            }

            $payload = [
                'tool_outputs' => [
                    [
                        'tool_call_id' => $toolCall->openai_tool_call_id,
                        'output' => json_encode(['status' => 'completed', 'message' => 'Block completed successfully']),
                    ]
                ]
            ];

            \Log::channel('project_intake')->info('Submitting tool outputs to OpenAI', [
                'payload' => $payload,
                'run_id' => $run->openai_run_id,
            ]);

            $response = Http::withHeaders($headers)
                ->post($this->baseUrl . '/threads/' . $run->thread->openai_thread_id . '/runs/' . $run->openai_run_id . '/submit_tool_outputs', $payload);

            if ($response->successful()) {
                $toolCall->update(['status' => 'completed']);
                \Log::channel('project_intake')->info('Tool output submitted successfully', [
                    'run_id' => $run->openai_run_id,
                    'tool_call_id' => $toolCall->openai_tool_call_id,
                ]);
            } else {
                \Log::channel('project_intake')->error('Tool output submission failed', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                    'run_id' => $run->openai_run_id,
                ]);
            }
        } catch (\Exception $e) {
            \Log::channel('project_intake')->error('Tool output submission error', [
                'error' => $e->getMessage(),
                'run_id' => $run->openai_run_id,
            ]);
        }
    }

    /**
     * Holt Messages von einem Thread
     */
    private function getThreadMessages(string $openaiThreadId): array
    {
        try {
            $headers = [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'OpenAI-Beta' => 'assistants=v2',
            ];

            if ($this->organization) {
                $headers['OpenAI-Organization'] = $this->organization;
            }

            $response = Http::withHeaders($headers)
                ->get($this->baseUrl . '/threads/' . $openaiThreadId . '/messages');

            if ($response->successful()) {
                return $response->json()['data'] ?? [];
            } else {
                Log::error('OpenAI Messages Fetch Failed', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);
                return [];
            }
        } catch (\Exception $e) {
            Log::error('Messages Fetch Error', [
                'error' => $e->getMessage(),
                'thread_id' => $openaiThreadId,
            ]);
            return [];
        }
    }
}
