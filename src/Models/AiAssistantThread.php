<?php

namespace Platform\AiAssistant\Models;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Uid\UuidV7;

class AiAssistantThread extends Model
{
    protected $table = 'ai_assistant_threads';
    
    protected $fillable = [
        'uuid',
        'openai_thread_id',
        'title', // Thread-Titel
        'assistant_id',
        'ai_model_id', // Interne Referenz zum aktuellen AI Model
        'status',
        'metadata',
        'team_id',
        'created_by_user_id',
        'user_id',
        'ownership_type',
        'context_type',
        'context_id',
        'context_metadata',
    ];
    
    protected $casts = [
        'metadata' => 'array',
        'context_metadata' => 'array',
    ];
    
    // Scopes für Ownership
    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId)->where('ownership_type', 'team');
    }
    
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId)->where('ownership_type', 'user');
    }
    
    public function scopeAccessibleBy($query, $user)
    {
        return $query->where(function($q) use ($user) {
            $q->where('team_id', $user->currentTeam?->id)
              ->where('ownership_type', 'team')
              ->orWhere('user_id', $user->id)
              ->where('ownership_type', 'user');
        });
    }
    
    public function isAccessibleBy($user)
    {
        return ($this->ownership_type === 'team' && $this->team_id === $user->currentTeam?->id) ||
               ($this->ownership_type === 'user' && $this->user_id === $user->id);
    }
    
    // Relationships
    public function assistant()
    {
        return $this->belongsTo(AiAssistantAssistant::class, 'assistant_id');
    }
    
    public function model()
    {
        return $this->belongsTo(AiAssistantModel::class, 'ai_model_id');
    }
    
    public function team()
    {
        return $this->belongsTo(\App\Models\Team::class);
    }
    
    public function createdByUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_user_id');
    }
    
    public function owner()
    {
        return $this->ownership_type === 'team' 
            ? $this->belongsTo(\App\Models\Team::class, 'team_id')
            : $this->belongsTo(\App\Models\User::class, 'user_id');
    }
    
    // Polymorphic Context
    public function context()
    {
        return $this->morphTo();
    }
    
    public function runs()
    {
        return $this->hasMany(AiAssistantRun::class, 'thread_id');
    }

    public function activeRun()
    {
        return $this->hasOne(AiAssistantRun::class, 'thread_id')
            ->whereIn('status', ['queued', 'in_progress', 'requires_action']);
    }
    
    public function messages()
    {
        return $this->hasMany(AiAssistantMessage::class, 'thread_id');
    }
    
    /**
     * Wechselt das Model für diesen Thread
     */
    public function switchModel(string $modelId): bool
    {
        $model = AiAssistantModel::where('model_id', $modelId)
            ->where('is_active', true)
            ->where('is_deprecated', false)
            ->first();
            
        if (!$model) {
            return false;
        }
        
        $this->update(['ai_model_id' => $model->id]);
        return true;
    }
    
    /**
     * Holt das aktuelle Model oder das Standard-Model des Assistants
     */
    public function getCurrentModel(): ?AiAssistantModel
    {
        if ($this->ai_model_id) {
            return $this->model;
        }
        
        // Fallback auf Assistant-Model
        return $this->assistant->model ?? null;
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                do {
                    $uuid = UuidV7::generate();
                } while (self::where('uuid', $uuid)->exists());
                
                $model->uuid = $uuid;
            }
        });
    }
}
