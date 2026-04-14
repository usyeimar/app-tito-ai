<?php

use App\Models\Tenant\KnowledgeBase\KnowledgeBase;
use App\Models\Tenant\KnowledgeBase\KnowledgeBaseCategory;
use App\Models\Tenant\KnowledgeBase\KnowledgeBaseDocument;

it('requires authentication to list documents', function () {
    $kb = KnowledgeBase::factory()->create();

    $response = $this->getJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}/documents"));
    $response->assertUnauthorized();
});

it('lists documents for a knowledge base', function () {
    $kb = KnowledgeBase::factory()->create();
    $category = KnowledgeBaseCategory::factory()->create(['knowledge_base_id' => $kb->id]);
    KnowledgeBaseDocument::factory()->count(3)->create(['knowledge_base_category_id' => $category->id]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}/documents"));

    $response->assertOk();
});

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

it('deletes a document', function () {
    $kb = KnowledgeBase::factory()->create();
    $category = KnowledgeBaseCategory::factory()->create(['knowledge_base_id' => $kb->id]);
    $document = KnowledgeBaseDocument::factory()->create(['knowledge_base_category_id' => $category->id]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}/documents/{$document->id}"));

    $response->assertNoContent();
    expect(KnowledgeBaseDocument::query()->whereKey($document->id)->exists())->toBeFalse();
});

it('returns 404 for non-existent document', function () {
    $kb = KnowledgeBase::factory()->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}/documents/01HX99999999999999999999999"));

    $response->assertNotFound();
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
