<?php

namespace App\Models\Tenant\KnowledgeBase;

use App\Models\Tenant\Auth\Authentication\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnowledgeBaseDocument extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = ['knowledge_base_category_id', 'title', 'slug', 'content_format', 'status', 'author_id', 'published_at'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseCategory::class, 'knowledge_base_category_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(KnowledgeBaseDocumentVersion::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
