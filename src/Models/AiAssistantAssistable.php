<?php

namespace Platform\AiAssistant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

class AiAssistantAssistable extends Model
{
    protected $table = 'ai_assistant_assistables';

    protected $fillable = [
        'uuid',
        'assistable_type',
        'assistable_id',
        'ai_assistant_id',
        'metadata',
        'team_id',
        'created_by_user_id',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function assistant(): BelongsTo
    {
        return $this->belongsTo(AiAssistantAssistant::class, 'ai_assistant_id');
    }

    public function assistable()
    {
        return $this->morphTo();
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'created_by_user_id');
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
