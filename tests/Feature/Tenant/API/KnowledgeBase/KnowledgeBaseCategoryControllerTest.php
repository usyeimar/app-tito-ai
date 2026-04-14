<?php

use App\Models\Tenant\KnowledgeBase\KnowledgeBase;
use App\Models\Tenant\KnowledgeBase\KnowledgeBaseCategory;

it('requires authentication to list categories', function () {
    $kb = KnowledgeBase::factory()->create();

    $response = $this->getJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}/categories"));
    $response->assertUnauthorized();
});

it('lists categories for a knowledge base', function () {
    $kb = KnowledgeBase::factory()->create();
    KnowledgeBaseCategory::factory()->count(3)->create(['knowledge_base_id' => $kb->id]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}/categories"));

    $response->assertOk();
});

it('creates a category', function () {
    $kb = KnowledgeBase::factory()->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}/categories"), [
            'knowledge_base_id' => (string) $kb->id,
            'name' => 'Test Category',
            'display_order' => 1,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Test Category');
    $response->assertJsonPath('data.knowledge_base_id', (string) $kb->id);
});

it('updates a category', function () {
    $kb = KnowledgeBase::factory()->create();
    $category = KnowledgeBaseCategory::factory()->create([
        'knowledge_base_id' => $kb->id,
        'name' => 'Original',
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}/categories/{$category->id}"), [
            'name' => 'Updated Category',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.name', 'Updated Category');

    expect($category->fresh()->name)->toBe('Updated Category');
});

it('deletes a category', function () {
    $kb = KnowledgeBase::factory()->create();
    $category = KnowledgeBaseCategory::factory()->create(['knowledge_base_id' => $kb->id]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}/categories/{$category->id}"));

    $response->assertNoContent();
    expect(KnowledgeBaseCategory::query()->whereKey($category->id)->exists())->toBeFalse();
});

it('returns 404 when updating non-existent category', function () {
    $kb = KnowledgeBase::factory()->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}/categories/01HX99999999999999999999999"), [
            'name' => 'Test',
        ]);

    $response->assertNotFound();
});

it('requires name to create category', function () {
    $kb = KnowledgeBase::factory()->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}/categories"), [
            'display_order' => 1,
        ]);

    $response->assertUnprocessable();
    assertHasValidationError($response, 'name');
});
