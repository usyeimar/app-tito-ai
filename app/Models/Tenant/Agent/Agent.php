<?php

declare(strict_types=1);

namespace App\Models\Tenant\Agent;

use App\Models\Tenant\KnowledgeBase\KnowledgeBase;
use Database\Factories\Tenant\Agent\AgentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use HasFactory, HasSlug, HasUlids;

    protected $table = 'agents';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'language',
        'tags',
        'timezone',
        'currency',
        'number_format',
        'knowledge_base_id',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    public function settings(): HasOne
    {
        return $this->hasOne(AgentSetting::class);
    }

    public function tools(): HasMany
    {
        return $this->hasMany(AgentTool::class);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(AgentDeployment::class);
    }

    public function trunks(): HasMany
    {
        return $this->hasMany(Trunk::class);
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }
}
