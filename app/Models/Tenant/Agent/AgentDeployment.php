<?php

declare(strict_types=1);

namespace App\Models\Tenant\Agent;

use Database\Factories\Tenant\Agent\AgentDeploymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentDeployment extends Model
{
    /** @use HasFactory<AgentDeploymentFactory> */
    use HasFactory, HasUlids;

    protected $table = 'agent_deployments';

    protected $fillable = [
        'agent_id',
        'channel',
        'enabled',
        'config',
        'version',
        'deployed_at',
        'status',
        'metadata',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'config' => 'array',
        'metadata' => 'array',
        'deployed_at' => 'datetime',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
