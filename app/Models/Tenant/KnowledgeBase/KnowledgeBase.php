<?php

namespace App\Models\Tenant\KnowledgeBase;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeBase extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = ['name', 'slug', 'description', 'is_public'];

    public function categories(): HasMany
    {
        return $this->hasMany(KnowledgeBaseCategory::class);
    }
}
