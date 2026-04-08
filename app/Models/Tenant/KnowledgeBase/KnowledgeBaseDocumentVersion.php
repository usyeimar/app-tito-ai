<?php

namespace App\Models\Tenant\KnowledgeBase;

use App\Models\Tenant\Auth\Authentication\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeBaseDocumentVersion extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = ['knowledge_base_document_id', 'version_number', 'content', 'author_id', 'change_summary'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseDocument::class, 'knowledge_base_document_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
