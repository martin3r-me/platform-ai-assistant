<?php

namespace Platform\AiAssistant\Models;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Uid\UuidV7;

class AiAssistantToolCall extends Model
{
    protected $table = 'ai_assistant_tool_calls';
    
    protected $fillable = [
        'openai_tool_call_id',
        'run_id',
        'thread_id',
        'type',
        'function_name',
        'function_arguments',
        'output',
        'status',
        'team_id',
        'created_by_user_id',
    ];
    
    protected $casts = [
        'function_arguments' => 'array',
        'output' => 'array',
        'metadata' => 'array',
    ];
    
    // Scopes für Ownership
    public function scopeForTeam($query, $teamId)
    {
        return $query->where('team_id', $teamId);
    }
    
    public function scopeForUser($query, $userId)
    {
        return $query->where('created_by_user_id', $userId);
    }
    
    public function scopeAccessibleBy($query, $user)
    {
        return $query->where(function($q) use ($user) {
            $q->where('team_id', $user->currentTeam?->id)
              ->orWhere('created_by_user_id', $user->id);
        });
    }
    
    // Relationships
    public function run()
    {
        return $this->belongsTo(AiAssistantRun::class, 'run_id');
    }
    
    public function team()
    {
        return $this->belongsTo(\App\Models\Team::class);
    }
    
    public function createdByUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_user_id');
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
