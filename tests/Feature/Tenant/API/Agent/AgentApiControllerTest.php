<?php

use App\Models\Central\Auth\Role\Role;
use App\Models\Tenant\Agent\Agent;
use App\Models\Tenant\Auth\Authentication\User;



describe('Agents API', function () {
    describe('Authentication and Authorization', function () {
        it('requires authentication to list agents', function () {
            $response = $this->getJson($this->tenantApiUrl('ai/agents'));
            $response->assertUnauthorized();
        });

        it('requires verified email to create an agent', function () {
            $role = Role::firstOrCreate([
                'name' => 'super_admin',
                'guard_name' => 'tenant',
            ]);

            $unverifiedUser = User::factory()->create([
                'email_verified_at' => null,
            ]);
            $unverifiedUser->assignRole($role);

            $response = $this->actingAs($unverifiedUser, 'tenant-api')
                ->postJson($this->tenantApiUrl('ai/agents'), [
                    'name' => 'Support Agent',
                ]);

            $response->assertForbidden();
        });
    });

    describe('Agent Configuration', function () {
        describe('List', function () {
            it('lists agents', function () {
                Agent::factory()->count(3)->create();

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->getJson($this->tenantApiUrl('ai/agents'));

                $response->assertOk();
                $response->assertJsonStructure([
                    'data' => [['id', 'name', 'slug', 'description', 'language', 'tags', 'timezone', 'currency', 'number_format', 'brain_config', 'runtime_config', 'architecture_config', 'capabilities_config', 'observability_config', 'created_at', 'updated_at']],
                ]);
                $response->assertJsonCount(3, 'data');
            });
        });

        describe('Create', function () {
            it('creates an agent and returns 201', function () {
                $response = $this->actingAs($this->user, 'tenant-api')
                    ->postJson($this->tenantApiUrl('ai/agents'), [
                        'name' => 'Support Agent',
                        'slug' => 'support-agent',
                        'description' => 'Helpful support agent',
                        'language' => 'es-CO',
                        'timezone' => 'America/Bogota',
                        'brain_config' => ['llm' => 'gpt-4d'],
                        'runtime_config' => [],
                        'architecture_config' => [],
                        'capabilities_config' => [],
                        'observability_config' => [],
                    ]);

                $response->assertCreated();
                $response->assertJsonPath('data.name', 'Support Agent');
                $response->assertJsonPath('data.slug', 'support-agent');
                $response->assertJsonPath('data.brain_config.llm', 'gpt-4d');
            });

            it('requires name to create an agent', function () {
                $response = $this->actingAs($this->user, 'tenant-api')
                    ->postJson($this->tenantApiUrl('ai/agents'), [
                        // name omitted
                    ]);

                assertHasValidationError($response, 'name');
            });
        });

        describe('Show', function () {
            it('shows a single agent', function () {
                $agent = Agent::factory()->create(['name' => 'VIP Agent']);

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->getJson($this->tenantApiUrl("ai/agents/{$agent->id}"));

                $response->assertOk();
                $response->assertJsonPath('data.id', $agent->id);
                $response->assertJsonPath('data.name', 'VIP Agent');
            });

            it('returns 404 for non-existent agent', function () {
                $response = $this->actingAs($this->user, 'tenant-api')
                    ->getJson($this->tenantApiUrl('ai/agents/99999999-9999-9999-9999-999999999999'));

                $response->assertNotFound();
            });
        });

        describe('Update', function () {
            it('updates an agent', function () {
                $agent = Agent::factory()->create(['name' => 'Original', 'timezone' => 'UTC']);

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->patchJson($this->tenantApiUrl("ai/agents/{$agent->id}"), [
                        'name' => 'Updated Name',
                        'timezone' => 'America/New_York',
                        'brain_config' => ['llm' => 'claude'],
                    ]);

                $response->assertOk();
                $response->assertJsonPath('data.name', 'Updated Name');
                $response->assertJsonPath('data.timezone', 'America/New_York');
                $response->assertJsonPath('data.brain_config.llm', 'claude');

                // Verify it was updated in the database
                expect($agent->fresh()->name)->toBe('Updated Name');
                expect($agent->fresh()->settings->brain_config)->toBe(['llm' => 'claude']);
            });
        });

        describe('Delete', function () {
            it('deletes an agent and returns 200', function () {
                $agent = Agent::factory()->create(['name' => 'Disposable']);

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->deleteJson($this->tenantApiUrl("ai/agents/{$agent->id}"));

                $response->assertOk();
                expect(Agent::query()->whereKey($agent->id)->exists())->toBeFalse();
            });
        });
    });




});
