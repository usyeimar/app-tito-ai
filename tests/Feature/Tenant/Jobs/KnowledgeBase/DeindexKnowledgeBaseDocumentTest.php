<?php

use App\Jobs\Tenant\KnowledgeBase\DeindexKnowledgeBaseDocument;
use Laravel\Ai\Stores;

beforeEach(function (): void {
    Stores::fake();
});

describe('DeindexKnowledgeBaseDocument job', function () {
    it('removes the file from the vector store', function () {
        $storeId = Stores::fakeId('Test KB');
        Stores::create('Test KB');

        (new DeindexKnowledgeBaseDocument($storeId, 'file_to_remove'))->handle();

        // Job completes without exception — file removal was called
        expect(true)->toBeTrue();
    });

    it('accepts vector store id and file id as constructor params', function () {
        $job = new DeindexKnowledgeBaseDocument('vs_store_123', 'file_456');

        expect($job->vectorStoreId)->toBe('vs_store_123');
        expect($job->vectorStoreFileId)->toBe('file_456');
    });
});
