<?php

namespace Platform\AiAssistant\Models;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Uid\UuidV7;

class AiAssistantAssistant extends Model
{
    protected $table = 'ai_assistant_assistants';
    
    protected $fillable = [
        'uuid',
        'openai_assistant_id',
        'name',
        'description',
        'instructions',
        'ai_model_id',
        'tools',
        'tool_resources',
        'metadata',
        'response_format',
        'temperature',
        'top_p',
        'reasoning_effort',
        'is_active',
        'team_id',
        'created_by_user_id',
        'user_id',
        'ownership_type',
    ];
    
    protected $casts = [
        'tools' => 'array',
        'tool_resources' => 'array',
        'metadata' => 'array',
        'response_format' => 'array',
        'temperature' => 'decimal:2',
        'top_p' => 'decimal:2',
        'is_active' => 'boolean',
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
    
    public function threads()
    {
        return $this->hasMany(AiAssistantThread::class, 'assistant_id');
    }
    
    public function model()
    {
        return $this->belongsTo(AiAssistantModel::class, 'ai_model_id');
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
