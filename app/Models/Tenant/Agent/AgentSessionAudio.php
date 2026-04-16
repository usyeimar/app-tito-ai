<?php

declare(strict_types=1);

namespace App\Models\Tenant\Agent;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentSessionAudio extends Model
{
    use HasUlids;

    protected $table = 'agent_session_audios';

    protected $fillable = [
        'agent_session_id',
        'name',
        'path',
        'mime_type',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AgentSession::class, 'agent_session_id');
    }
}
