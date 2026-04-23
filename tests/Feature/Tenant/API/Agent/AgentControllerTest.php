<?php

use App\Models\Tenant\Agent\Agent;
use App\Models\Tenant\KnowledgeBase\KnowledgeBase;

describe('Agent API', function () {
    describe('Authentication', function () {
        it('requires authentication to list agents', function () {
            $response = $this->getJson($this->tenantApiUrl('ai/agents'));
            $response->assertUnauthorized();
        });

        it('requires authentication to create an agent', function () {
            $response = $this->postJson($this->tenantApiUrl('ai/agents'), [
                'name' => 'Test Agent',
            ]);
            $response->assertUnauthorized();
        });
    });

    describe('Agent Management', function () {
        describe('List', function () {
            it('lists agents', function () {
                Agent::factory()->count(3)->create();

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->getJson($this->tenantApiUrl('ai/agents'));

                $response->assertOk();
                $response->assertJsonStructure([
                    'data' => [[
                        'id',
                        'name',
                        'slug',
                        'description',
                        'language',
                        'tags',
                        'timezone',
                        'currency',
                        'number_format',
                        'knowledge_base_id',
                        'created_at',
                        'updated_at',
                    ]],
                ]);
                $response->assertJsonCount(3, 'data');
            });

            it('lists agents with knowledge base', function () {
                $kb = KnowledgeBase::factory()->create();
                Agent::factory()->create(['knowledge_base_id' => $kb->id]);
                Agent::factory()->create(['knowledge_base_id' => null]);

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->getJson($this->tenantApiUrl('ai/agents'));

                $response->assertOk();
                $response->assertJsonCount(2, 'data');
            });
        });

        describe('Show', function () {
            it('shows an agent', function () {
                $agent = Agent::factory()->create(['name' => 'Test Agent']);

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->getJson($this->tenantApiUrl("ai/agents/{$agent->id}"));

                $response->assertOk();
                $response->assertJsonPath('data.id', (string) $agent->id);
                $response->assertJsonPath('data.name', 'Test Agent');
            });

            it('returns 404 for non-existent agent', function () {
                $response = $this->actingAs($this->user, 'tenant-api')
                    ->getJson($this->tenantApiUrl('ai/agents/01HX99999999999999999999999'));

                $response->assertNotFound();
            });
        });

        describe('Create', function () {
            it('creates an agent', function () {
                $response = $this->actingAs($this->user, 'tenant-api')
                    ->postJson($this->tenantApiUrl('ai/agents'), [
                        'name' => 'New Agent',
                        'language' => 'en',
                        'timezone' => 'America/New_York',
                    ]);

                $response->assertCreated();
                $response->assertJsonPath('data.name', 'New Agent');
                $response->assertJsonPath('data.language', 'en');
            });

            it('creates an agent without name and generates a default one', function () {
                $response = $this->actingAs($this->user, 'tenant-api')
                    ->postJson($this->tenantApiUrl('ai/agents'), [
                        'language' => 'en',
                    ]);

                $response->assertCreated();
                expect($response->json('data.name'))->toStartWith('Nuevo Agente');
                expect($response->json('data.language'))->toBe('en');
            });
        });

        describe('Update', function () {
            it('updates an agent', function () {
                $agent = Agent::factory()->create(['name' => 'Original']);

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->patchJson($this->tenantApiUrl("ai/agents/{$agent->id}"), [
                        'name' => 'Updated Agent',
                    ]);

                $response->assertOk();
                $response->assertJsonPath('data.name', 'Updated Agent');

                expect($agent->fresh()->name)->toBe('Updated Agent');
            });
        });

        describe('Delete', function () {
            it('deletes an agent ', function () {
                $agent = Agent::factory()->create();

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->deleteJson($this->tenantApiUrl("ai/agents/{$agent->id}"));

                $response->assertNoContent();
                expect(Agent::query()->whereKey($agent->id)->exists())->toBeFalse();
            });
        });
    });
});
