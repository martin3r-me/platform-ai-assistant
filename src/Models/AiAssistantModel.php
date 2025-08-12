<?php

namespace Platform\AiAssistant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Symfony\Component\Uid\UuidV7;

class AiAssistantModel extends Model
{
    use HasFactory;

    protected $table = 'ai_assistant_models';

    protected $fillable = [
        'uuid',
        'model_id',
        'name',
        'description',
        'category',
        'is_active',
        'is_deprecated',
        'deprecated_at',
        'api_metadata',
        'last_api_check',
        'team_id',
        'created_by_user_id',
    ];

    protected $guarded = ['id']; // Verhindert, dass 'id' überschrieben wird
    
    // Verhindert, dass UUID in 'id' Spalte geschrieben wird
    protected $primaryKey = 'id';
    public $incrementing = true;

    protected $casts = [
        'is_active' => 'boolean',
        'is_deprecated' => 'boolean',
        'deprecated_at' => 'datetime',
        'api_metadata' => 'array',
        'last_api_check' => 'datetime',
    ];

    // Relationships
    public function assistants(): HasMany
    {
        return $this->hasMany(AiAssistantAssistant::class, 'model_id', 'model_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('is_deprecated', false);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_active', true)
                    ->where('is_deprecated', false)
                    ->whereNull('deprecated_at');
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function scopeForTeam($query, $teamId)
    {
        return $query->where(function($q) use ($teamId) {
            $q->where('team_id', $teamId)
              ->orWhereNull('team_id'); // Globale Modelle
        });
    }

    public function scopeForUser($query, $user)
    {
        return $query->where(function($q) use ($user) {
            $q->where('team_id', $user->current_team_id)
              ->orWhereNull('team_id'); // Globale Modelle
        });
    }

    public function scopeAccessibleBy($query, $user)
    {
        return $query->where(function($q) use ($user) {
            $q->where('team_id', $user->current_team_id)
              ->orWhereNull('team_id'); // Globale Modelle
        });
    }

    // Methods
    public function isAvailable(): bool
    {
        return $this->is_active && !$this->is_deprecated && !$this->deprecated_at;
    }

    public function markAsDeprecated(): void
    {
        $this->update([
            'is_deprecated' => true,
            'deprecated_at' => now(),
        ]);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name . ' (' . $this->model_id . ')';
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
