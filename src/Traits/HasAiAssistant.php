<?php

namespace Platform\AiAssistant\Traits;

use Platform\AiAssistant\Models\AiAssistantThread;
use Platform\AiAssistant\Models\AiAssistantAssistable;
use Platform\AiAssistant\Models\AiAssistantAssistant;

trait HasAiAssistant
{
    /**
     * Get all AI Assistant threads for this model
     */
    public function aiAssistantThreads()
    {
        return $this->morphMany(AiAssistantThread::class, 'context');
    }
    
    /**
     * Get the active AI Assistant thread for this model
     */
    public function activeAiAssistantThread()
    {
        return $this->morphOne(AiAssistantThread::class, 'context')
            ->where('status', 'active');
    }
    
    /**
     * Create a new AI Assistant thread for this model
     */
    public function createAiAssistantThread($assistantId, $metadata = [])
    {
        return $this->aiAssistantThreads()->create([
            'assistant_id' => $assistantId,
            'status' => 'active',
            'context_metadata' => $metadata,
            'team_id' => $this->team_id ?? null,
            'created_by_user_id' => auth()->id(),
            'ownership_type' => $this->team_id ? 'team' : 'user',
            'user_id' => $this->team_id ? null : auth()->id(),
        ]);
    }
    
    /**
     * Get or create an AI Assistant thread for this model
     */
    public function getOrCreateAiAssistantThread($assistantId, $metadata = [])
    {
        return $this->activeAiAssistantThread()->firstOrCreate([
            'assistant_id' => $assistantId,
        ], [
            'status' => 'active',
            'context_metadata' => $metadata,
            'team_id' => $this->team_id ?? null,
            'created_by_user_id' => auth()->id(),
            'ownership_type' => $this->team_id ? 'team' : 'user',
            'user_id' => $this->team_id ? null : auth()->id(),
        ]);
    }



    /**
     * Bequemer Zugriff auf den zugewiesenen Assistant (oder null)
     */
    public function assignedAiAssistant()
    {
        return $this->belongsToMany(AiAssistantAssistant::class, 'ai_assistant_assistables', 'assistable_id', 'ai_assistant_id')
            ->where('assistable_type', get_class($this))
            ->withPivot(['metadata', 'team_id', 'created_by_user_id'])
            ->withTimestamps();
    }

    /**
     * Assistant zuweisen/ersetzen
     */
    public function setAssignedAiAssistant(int $assistantId, array $metadata = []): void
    {
        // Erst alle bestehenden Zuweisungen entfernen
        $this->assignedAiAssistant()->detach();
        
        // Neue Zuweisung erstellen
        $this->assignedAiAssistant()->attach($assistantId, [
            'uuid' => \Symfony\Component\Uid\UuidV7::generate(),
            'assistable_type' => get_class($this),
            'metadata' => json_encode($metadata),
            'team_id' => $this->team_id ?? null,
            'created_by_user_id' => auth()->id(),
        ]);
    }

    /**
     * Alle verfügbaren Assistants für das aktuelle Team/den User
     */
    public function availableAiAssistants()
    {
        $user = auth()->user();
        return AiAssistantAssistant::query()
            ->where(function ($q) use ($user) {
                $q->where('ownership_type', 'team')
                  ->where('team_id', $user->current_team_id)
                  ->orWhere(function ($q2) use ($user) {
                      $q2->where('ownership_type', 'user')
                         ->where('user_id', $user->id);
                  });
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }
}
