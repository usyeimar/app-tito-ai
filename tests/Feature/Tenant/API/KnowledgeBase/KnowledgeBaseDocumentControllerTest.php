<?php

use App\Jobs\Tenant\KnowledgeBase\DeindexKnowledgeBaseDocument;
use App\Jobs\Tenant\KnowledgeBase\IngestKnowledgeBaseDocument;
use App\Models\Tenant\KnowledgeBase\KnowledgeBase;
use App\Models\Tenant\KnowledgeBase\KnowledgeBaseCategory;
use App\Models\Tenant\KnowledgeBase\KnowledgeBaseDocument;
use Illuminate\Support\Facades\Bus;

beforeEach(function (): void {
    Bus::fake([IngestKnowledgeBaseDocument::class, DeindexKnowledgeBaseDocument::class]);
});

describe('Knowledge Base Document API', function () {
    describe('Authentication', function () {
        it('requires authentication to list documents', function () {
            $kb = KnowledgeBase::factory()->create();

            $response = $this->getJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}/documents"));
            $response->assertUnauthorized();
        });
    });

    describe('Document Management', function () {
        describe('List', function () {
            it('lists documents for a knowledge base', function () {
                $kb = KnowledgeBase::factory()->create();
                $category = KnowledgeBaseCategory::factory()->create(['knowledge_base_id' => $kb->id]);
                KnowledgeBaseDocument::factory()->count(3)->create(['knowledge_base_category_id' => $category->id]);

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->getJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}/documents"));

                $response->assertOk();
            });
        });

        describe('Show', function () {
            it('shows a document', function () {
                $kb = KnowledgeBase::factory()->create();
                $category = KnowledgeBaseCategory::factory()->create(['knowledge_base_id' => $kb->id]);
                $document = KnowledgeBaseDocument::factory()->create([
                    'knowledge_base_category_id' => $category->id,
                    'title' => 'Test Document',
                ]);

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->getJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}/documents/{$document->id}"));

                $response->assertOk();
                $response->assertJsonPath('data.id', (string) $document->id);
                $response->assertJsonPath('data.title', 'Test Document');
            });

            it('returns 404 for non-existent document', function () {
                $kb = KnowledgeBase::factory()->create();

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->getJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}/documents/01HX99999999999999999999999"));

                $response->assertNotFound();
            });
        });

        describe('Create', function () {
            it('creates a document', function () {
                $kb = KnowledgeBase::factory()->create();
                $category = KnowledgeBaseCategory::factory()->create(['knowledge_base_id' => $kb->id]);

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->postJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}/documents"), [
                        'knowledge_base_category_id' => (string) $category->id,
                        'title' => 'New Document',
                        'content' => '# Hello World',
                        'content_format' => 'markdown',
                    ]);

                $response->assertCreated();
                $response->assertJsonPath('data.title', 'New Document');
                $response->assertJsonPath('data.status', 'draft');
            });

            it('requires category to create document', function () {
                $kb = KnowledgeBase::factory()->create();

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->postJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}/documents"), [
                        'title' => 'No Category',
                        'content' => 'Content',
                    ]);

                $response->assertUnprocessable();
                assertHasValidationError($response, 'knowledge_base_category_id');
            });

            it('requires title to create document', function () {
                $kb = KnowledgeBase::factory()->create();
                $category = KnowledgeBaseCategory::factory()->create(['knowledge_base_id' => $kb->id]);

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->postJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}/documents"), [
                        'knowledge_base_category_id' => (string) $category->id,
                        'content' => 'Content',
                    ]);

                $response->assertUnprocessable();
                assertHasValidationError($response, 'title');
            });
        });

        describe('Update', function () {
            it('updates a document', function () {
                $kb = KnowledgeBase::factory()->create();
                $category = KnowledgeBaseCategory::factory()->create(['knowledge_base_id' => $kb->id]);
                $document = KnowledgeBaseDocument::factory()->create([
                    'knowledge_base_category_id' => $category->id,
                    'title' => 'Original Title',
                ]);

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->patchJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}/documents/{$document->id}"), [
                        'title' => 'Updated Title',
                    ]);

                $response->assertOk();
                $response->assertJsonPath('data.title', 'Updated Title');

                expect($document->fresh()->title)->toBe('Updated Title');
            });

            it('dispatches re-ingestion when title changes', function () {
                $kb = KnowledgeBase::factory()->create();
                $category = KnowledgeBaseCategory::factory()->create(['knowledge_base_id' => $kb->id]);
                $document = KnowledgeBaseDocument::factory()->create([
                    'knowledge_base_category_id' => $category->id,
                    'title' => 'Original',
                ]);

                $this->actingAs($this->user, 'tenant-api')
                    ->patchJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}/documents/{$document->id}"), [
                        'title' => 'New Title',
                    ])
                    ->assertOk();

                Bus::assertDispatched(IngestKnowledgeBaseDocument::class, fn ($job) => $job->documentId === $document->id);
            });

            it('dispatches re-ingestion when content changes', function () {
                $kb = KnowledgeBase::factory()->create();
                $category = KnowledgeBaseCategory::factory()->create(['knowledge_base_id' => $kb->id]);
                $document = KnowledgeBaseDocument::factory()->create([
                    'knowledge_base_category_id' => $category->id,
                ]);

                $this->actingAs($this->user, 'tenant-api')
                    ->patchJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}/documents/{$document->id}"), [
                        'content' => '# Updated content',
                    ])
                    ->assertOk();

                Bus::assertDispatched(IngestKnowledgeBaseDocument::class, fn ($job) => $job->documentId === $document->id);
            });

            it('does not dispatch re-ingestion when only status changes', function () {
                $kb = KnowledgeBase::factory()->create();
                $category = KnowledgeBaseCategory::factory()->create(['knowledge_base_id' => $kb->id]);
                $document = KnowledgeBaseDocument::factory()->create([
                    'knowledge_base_category_id' => $category->id,
                    'status' => 'draft',
                ]);

                $this->actingAs($this->user, 'tenant-api')
                    ->patchJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}/documents/{$document->id}"), [
                        'status' => 'published',
                    ])
                    ->assertOk();

                Bus::assertNotDispatched(IngestKnowledgeBaseDocument::class);
            });
        });

        describe('Delete', function () {
            it('deletes a document', function () {
                $kb = KnowledgeBase::factory()->create();
                $category = KnowledgeBaseCategory::factory()->create(['knowledge_base_id' => $kb->id]);
                $document = KnowledgeBaseDocument::factory()->create(['knowledge_base_category_id' => $category->id]);

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->deleteJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}/documents/{$document->id}"));

                $response->assertNoContent();
                expect(KnowledgeBaseDocument::query()->whereKey($document->id)->exists())->toBeFalse();
            });

            it('dispatches deindex job when deleting an indexed document', function () {
                $kb = KnowledgeBase::factory()->create(['vector_store_id' => 'vs_test_123']);
                $category = KnowledgeBaseCategory::factory()->create(['knowledge_base_id' => $kb->id]);
                $document = KnowledgeBaseDocument::factory()->create([
                    'knowledge_base_category_id' => $category->id,
                    'vector_store_file_id' => 'file_test_456',
                    'indexing_status' => 'indexed',
                ]);

                $this->actingAs($this->user, 'tenant-api')
                    ->deleteJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}/documents/{$document->id}"))
                    ->assertNoContent();

                Bus::assertDispatched(DeindexKnowledgeBaseDocument::class, function ($job) {
                    return $job->vectorStoreId === 'vs_test_123'
                        && $job->vectorStoreFileId === 'file_test_456';
                });
            });

            it('does not dispatch deindex job when document was never indexed', function () {
                $kb = KnowledgeBase::factory()->create(['vector_store_id' => null]);
                $category = KnowledgeBaseCategory::factory()->create(['knowledge_base_id' => $kb->id]);
                $document = KnowledgeBaseDocument::factory()->create([
                    'knowledge_base_category_id' => $category->id,
                    'vector_store_file_id' => null,
                ]);

                $this->actingAs($this->user, 'tenant-api')
                    ->deleteJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}/documents/{$document->id}"))
                    ->assertNoContent();

                Bus::assertNotDispatched(DeindexKnowledgeBaseDocument::class);
            });
        });
    });
});
