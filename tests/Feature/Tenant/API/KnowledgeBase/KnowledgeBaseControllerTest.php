<?php

use App\Models\Tenant\KnowledgeBase\KnowledgeBase;
use Laravel\Ai\Stores;

beforeEach(function (): void {
    Stores::fake();
});

describe('Knowledge Base API', function () {
    describe('Authentication', function () {
        it('requires authentication to list knowledge bases', function () {
            $response = $this->getJson($this->tenantApiUrl('ai/knowledge-bases'));
            $response->assertUnauthorized();
        });

        it('requires authentication to create a knowledge base', function () {
            $response = $this->postJson($this->tenantApiUrl('ai/knowledge-bases'), [
                'name' => 'Test KB',
            ]);
            $response->assertUnauthorized();
        });
    });

    describe('Knowledge Base Management', function () {
        describe('List', function () {
            it('lists knowledge bases', function () {
                KnowledgeBase::factory()->count(3)->create();

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->getJson($this->tenantApiUrl('ai/knowledge-bases'));

                $response->assertOk();
            });
        });

        describe('Create', function () {
            it('creates a knowledge base', function () {
                $response = $this->actingAs($this->user, 'tenant-api')
                    ->postJson($this->tenantApiUrl('ai/knowledge-bases'), [
                        'name' => 'My Knowledge Base',
                        'description' => 'A test knowledge base',
                        'is_public' => true,
                    ]);

                $response->assertCreated();
                $response->assertJsonPath('data.name', 'My Knowledge Base');
                $response->assertJsonPath('data.is_public', true);
            });

            it('requires name to create knowledge base', function () {
                $response = $this->actingAs($this->user, 'tenant-api')
                    ->postJson($this->tenantApiUrl('ai/knowledge-bases'), [
                        'description' => 'No name provided',
                    ]);

                $response->assertUnprocessable();
                assertHasValidationError($response, 'name');
            });
        });

        describe('Show', function () {
            it('shows a knowledge base', function () {
                $kb = KnowledgeBase::factory()->create([
                    'name' => 'Test KB',
                    'description' => 'Test description',
                ]);

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->getJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}"));

                $response->assertOk();
                $response->assertJsonPath('data.id', (string) $kb->id);
                $response->assertJsonPath('data.name', 'Test KB');
            });

            it('returns 404 for non-existent knowledge base', function () {
                $response = $this->actingAs($this->user, 'tenant-api')
                    ->getJson($this->tenantApiUrl('ai/knowledge-bases/01HX99999999999999999999999'));

                $response->assertNotFound();
            });
        });

        describe('Update', function () {
            it('updates a knowledge base', function () {
                $kb = KnowledgeBase::factory()->create(['name' => 'Original']);

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->patchJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}"), [
                        'name' => 'Updated KB',
                    ]);

                $response->assertOk();
                $response->assertJsonPath('data.name', 'Updated KB');

                expect($kb->fresh()->name)->toBe('Updated KB');
            });
        });

        describe('Delete', function () {
            it('deletes a knowledge base', function () {
                $kb = KnowledgeBase::factory()->create();

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->deleteJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}"));

                $response->assertNoContent();
                expect(KnowledgeBase::query()->whereKey($kb->id)->exists())->toBeFalse();
            });

            it('deletes the vector store when deleting a knowledge base', function () {
                $kb = KnowledgeBase::factory()->create(['vector_store_id' => Stores::fakeId('Test KB')]);

                $this->actingAs($this->user, 'tenant-api')
                    ->deleteJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}"))
                    ->assertNoContent();

                Stores::assertDeleted($kb->vector_store_id);
            });

            it('succeeds even when vector store deletion fails', function () {
                $kb = KnowledgeBase::factory()->create(['vector_store_id' => null]);

                $this->actingAs($this->user, 'tenant-api')
                    ->deleteJson($this->tenantApiUrl("ai/knowledge-bases/{$kb->id}"))
                    ->assertNoContent();

                Stores::assertNothingDeleted();
            });
        });
    });
});
