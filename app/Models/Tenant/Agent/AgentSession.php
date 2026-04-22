<?php

declare(strict_types=1);

namespace App\Models\Tenant\Agent;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgentSession extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'agent_sessions';

    protected $fillable = [
        'agent_id',
        'channel',
        'external_session_id',
        'status',
        'metadata',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function transcripts(): HasMany
    {
        return $this->hasMany(AgentSessionTranscript::class);
    }

    public function audio(): HasMany
    {
        return $this->hasMany(AgentSessionAudio::class);
    }
}
