<?php

declare(strict_types=1);

namespace App\Models\Tenant\Agent;

use Database\Factories\Tenant\Agent\AgentToolFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentTool extends Model
{
    /** @use HasFactory<AgentToolFactory> */
    use HasFactory, HasUlids;

    protected $table = 'agent_tools';

    protected $fillable = [
        'agent_id',
        'name',
        'description',
        'parameters',
        'is_active',
    ];

    protected $casts = [
        'parameters' => 'array',
        'is_active' => 'boolean',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
