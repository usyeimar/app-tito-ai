<?php

declare(strict_types=1);

namespace App\Jobs\Tenant\KnowledgeBase;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Stores;
use Throwable;

final class DeindexKnowledgeBaseDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [10, 30, 120];

    public function __construct(
        public string $vectorStoreId,
        public string $vectorStoreFileId,
    ) {}

    public function handle(): void
    {
        try {
            $store = Stores::get($this->vectorStoreId);
            $store->remove($this->vectorStoreFileId, deleteFile: true);
        } catch (Throwable $e) {
            Log::error('Failed to deindex knowledge base document from vector store.', [
                'vector_store_id' => $this->vectorStoreId,
                'vector_store_file_id' => $this->vectorStoreFileId,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
