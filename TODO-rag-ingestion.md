# TODO — KnowledgeBase RAG Ingestion

Pipeline: chunking → embeddings → vector store upload, wired into `CreateKnowledgeBase` + `CreateKnowledgeBaseDocument` via queued jobs. Uses Laravel AI SDK (`laravel/ai`).

## Schema (tenant migrations)
- [x] `knowledge_bases` → add `vector_store_id` (nullable string)
- [x] `knowledge_base_documents` → add `vector_store_file_id` (nullable string), `indexing_status` (string, default `pending`), `indexed_at` (nullable timestamp), `indexing_error` (nullable text)

## Model updates
- [x] `KnowledgeBase`: add `vector_store_id` to `$fillable`
- [x] `KnowledgeBaseDocument`: add new columns to `$fillable` + `$casts` for `indexed_at`

## Actions
- [x] `CreateKnowledgeBase` → after `create()`, `Stores::create($kb->name)` and persist `vector_store_id`. Fail soft in tests (tolerate faked stores).
- [x] `CreateKnowledgeBaseDocument` → after transaction, `dispatch(new IngestKnowledgeBaseDocument($doc->id))`
- [x] `UpdateKnowledgeBaseDocument` → re-dispatches `IngestKnowledgeBaseDocument` when title or content changes
- [x] `DeleteKnowledgeBaseDocument` → dispatches `DeindexKnowledgeBaseDocument` job when document has vector_store_file_id
- [x] `DeleteKnowledgeBase` → calls `Stores::delete($kb->vector_store_id)` (fail-soft)

## Job
- [x] `App\Jobs\Tenant\KnowledgeBase\IngestKnowledgeBaseDocument` (ShouldQueue, tries=3)
  - Load document + latest version + kb
  - If kb has no `vector_store_id` → create + persist
  - If document has prior `vector_store_file_id` → `$store->remove($fileId, deleteFile: true)`
  - Build markdown payload (`# {title}\n\n{content}`)
  - `Document::fromString($payload, 'text/markdown')->put()` → file id
  - `$store->add($file->id)`
  - Update document: `vector_store_file_id`, `indexing_status='indexed'`, `indexed_at=now()`
  - On failure → `indexing_status='failed'`, `indexing_error=$throwable->getMessage()`

- [x] `App\Jobs\Tenant\KnowledgeBase\DeindexKnowledgeBaseDocument` (ShouldQueue, tries=3)
  - Accepts `vectorStoreId` and `vectorStoreFileId`
  - `Stores::get($vectorStoreId)->remove($vectorStoreFileId, deleteFile: true)`
  - On failure → logs error and re-throws for retry

## Config
- Uses `config/ai.php` defaults (`openai` for embeddings). Only env required: `OPENAI_API_KEY`.
- File search / vector stores currently live on OpenAI provider.

## Tests (Pest)
- [x] `CreateKnowledgeBaseTest`: `Stores::fake()`, assert store created with kb name, assert `vector_store_id` populated
- [x] `IngestKnowledgeBaseDocumentTest`: `Stores::fake()`, `Files::fake()`; run job, assert file added, document marks `indexed`

## Future work
- Chunking strategy (markdown-aware splitter, ~800 tokens, overlap 100) if we later want chunk-level citations
- `AllowedFilter`-powered search across indexed knowledge
- Webhook / polling to confirm store indexing readiness
