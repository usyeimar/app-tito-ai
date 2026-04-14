<?php

use App\Models\Tenant\Agent\Trunk;

describe('Trunk API', function () {
    describe('Authentication and Authorization', function () {
        it('requires authentication to list trunks', function () {
            $response = $this->getJson($this->tenantApiUrl('ai/trunks'));
            $response->assertUnauthorized();
        });

        it('requires authentication to create a trunk', function () {
            $response = $this->postJson($this->tenantApiUrl('ai/trunks'), [
                'name' => 'Test Trunk',
                'mode' => Trunk::MODE_INBOUND,
            ]);
            $response->assertUnauthorized();
        });

        it('requires authentication to view a trunk', function () {
            $trunk = Trunk::factory()->create();

            $response = $this->getJson($this->tenantApiUrl("ai/trunks/{$trunk->id}"));
            $response->assertUnauthorized();
        });

        it('requires authentication to update a trunk', function () {
            $trunk = Trunk::factory()->create();

            $response = $this->patchJson($this->tenantApiUrl("ai/trunks/{$trunk->id}"), [
                'name' => 'Updated Name',
            ]);
            $response->assertUnauthorized();
        });

        it('requires authentication to delete a trunk', function () {
            $trunk = Trunk::factory()->create();

            $response = $this->deleteJson($this->tenantApiUrl("ai/trunks/{$trunk->id}"));
            $response->assertUnauthorized();
        });
    });

    describe('Trunk Management', function () {
        describe('List', function () {
            it('lists trunks', function () {
                Trunk::factory()->count(3)->create();

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->getJson($this->tenantApiUrl('ai/trunks'));

                $response->assertOk();
                $response->assertJsonStructure([
                    'data' => [[
                        'id',
                        'name',
                        'agent_id',
                        'workspace_slug',
                        'mode',
                        'max_concurrent_calls',
                        'codecs',
                        'status',
                        'inbound_auth',
                        'routes',
                        'sip_host',
                        'sip_port',
                        'register_config',
                        'outbound',
                        'created_at',
                        'updated_at',
                    ]],
                ]);
                $response->assertJsonCount(3, 'data');
            });
        });

        describe('Create', function () {
            it('creates a trunk and returns 201', function () {
                $response = $this->actingAs($this->user, 'tenant-api')
                    ->postJson($this->tenantApiUrl('ai/trunks'), [
                        'name' => 'SIP Trunk',
                        'mode' => Trunk::MODE_INBOUND,
                        'workspace_slug' => 'default',
                        'max_concurrent_calls' => 10,
                        'codecs' => ['ulaw', 'alaw'],
                        'status' => Trunk::STATUS_ACTIVE,
                        'sip_host' => 'sip.example.com',
                        'sip_port' => 5060,
                        'inbound_auth' => [
                            'auth_type' => 'ip',
                            'allowed_ips' => ['192.168.1.0/24'],
                        ],
                        'routes' => [
                            [
                                'pattern' => '*',
                                'agent_id' => null,
                                'priority' => 0,
                                'enabled' => true,
                            ],
                        ],
                    ]);

                $response->assertCreated();
                $response->assertJsonPath('data.name', 'SIP Trunk');
                $response->assertJsonPath('data.mode', Trunk::MODE_INBOUND);
                $response->assertJsonPath('data.workspace_slug', 'default');
                $response->assertJsonPath('data.max_concurrent_calls', 10);
                $response->assertJsonPath('data.codecs', ['ulaw', 'alaw']);
                $response->assertJsonPath('data.status', Trunk::STATUS_ACTIVE);
                $response->assertJsonPath('data.sip_host', 'sip.example.com');
                $response->assertJsonPath('data.sip_port', 5060);
                $response->assertJsonPath('message', 'Trunk created');
            });

            it('creates a register mode trunk', function () {
                $response = $this->actingAs($this->user, 'tenant-api')
                    ->postJson($this->tenantApiUrl('ai/trunks'), [
                        'name' => 'Register Trunk',
                        'mode' => Trunk::MODE_REGISTER,
                        'workspace_slug' => 'default',
                        'sip_host' => 'sip.example.com',
                        'sip_port' => 5060,
                        'register_config' => [
                            'server' => 'sip.example.com',
                            'port' => 5060,
                            'username' => 'testuser',
                            'password' => 'testpass',
                            'register_interval' => 60,
                        ],
                    ]);

                $response->assertCreated();
                $response->assertJsonPath('data.mode', Trunk::MODE_REGISTER);
                $response->assertJsonPath('data.register_config.server', 'sip.example.com');
                $response->assertJsonPath('data.register_config.username', 'testuser');
            });

            it('creates an outbound mode trunk', function () {
                $response = $this->actingAs($this->user, 'tenant-api')
                    ->postJson($this->tenantApiUrl('ai/trunks'), [
                        'name' => 'Outbound Trunk',
                        'mode' => Trunk::MODE_OUTBOUND,
                        'workspace_slug' => 'default',
                        'outbound' => [
                            'trunk_name' => 'Provider ABC',
                            'server' => 'sip.outbound.com',
                            'port' => 5060,
                            'username' => 'outuser',
                            'password' => 'outpass',
                            'caller_id' => '+1234567890',
                        ],
                    ]);

                $response->assertCreated();
                $response->assertJsonPath('data.mode', Trunk::MODE_OUTBOUND);
                $response->assertJsonPath('data.outbound.trunk_name', 'Provider ABC');
                $response->assertJsonPath('data.outbound.caller_id', '+1234567890');
            });

            it('requires name to create a trunk', function () {
                $response = $this->actingAs($this->user, 'tenant-api')
                    ->postJson($this->tenantApiUrl('ai/trunks'), [
                        'mode' => Trunk::MODE_INBOUND,
                    ]);

                assertHasValidationError($response, 'name');
            });

            it('requires valid mode to create a trunk', function () {
                $response = $this->actingAs($this->user, 'tenant-api')
                    ->postJson($this->tenantApiUrl('ai/trunks'), [
                        'name' => 'Invalid Trunk',
                        'mode' => 'invalid_mode',
                    ]);

                assertHasValidationError($response, 'mode');
            });
        });

        describe('Show', function () {
            it('shows a single trunk', function () {
                $trunk = Trunk::factory()->create([
                    'name' => 'My Trunk',
                    'mode' => Trunk::MODE_INBOUND,
                    'sip_host' => 'sip.mytrunk.com',
                ]);

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->getJson($this->tenantApiUrl("ai/trunks/{$trunk->id}"));

                $response->assertOk();
                $response->assertJsonPath('data.id', $trunk->id);
                $response->assertJsonPath('data.name', 'My Trunk');
                $response->assertJsonPath('data.mode', Trunk::MODE_INBOUND);
                $response->assertJsonPath('data.sip_host', 'sip.mytrunk.com');
            });

            it('returns 404 for non-existent trunk', function () {
                $response = $this->actingAs($this->user, 'tenant-api')
                    ->getJson($this->tenantApiUrl('ai/trunks/99999999-9999-9999-9999-999999999999'));

                $response->assertNotFound();
            });
        });

        describe('Update', function () {
            it('updates a trunk', function () {
                $trunk = Trunk::factory()->create([
                    'name' => 'Original',
                    'status' => Trunk::STATUS_ACTIVE,
                    'max_concurrent_calls' => 5,
                ]);

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->patchJson($this->tenantApiUrl("ai/trunks/{$trunk->id}"), [
                        'name' => 'Updated Trunk',
                        'status' => Trunk::STATUS_INACTIVE,
                        'max_concurrent_calls' => 20,
                    ]);

                $response->assertOk();
                $response->assertJsonPath('data.name', 'Updated Trunk');
                $response->assertJsonPath('data.status', Trunk::STATUS_INACTIVE);
                $response->assertJsonPath('data.max_concurrent_calls', 20);
                $response->assertJsonPath('message', 'Trunk updated');

                expect($trunk->fresh()->name)->toBe('Updated Trunk');
                expect($trunk->fresh()->status)->toBe(Trunk::STATUS_INACTIVE);
                expect($trunk->fresh()->max_concurrent_calls)->toBe(20);
            });

            it('updates trunk routes', function () {
                $trunk = Trunk::factory()->create();

                $newRoutes = [
                    [
                        'pattern' => '100',
                        'agent_id' => null,
                        'priority' => 1,
                        'enabled' => true,
                    ],
                    [
                        'pattern' => '200',
                        'agent_id' => null,
                        'priority' => 2,
                        'enabled' => false,
                    ],
                ];

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->patchJson($this->tenantApiUrl("ai/trunks/{$trunk->id}"), [
                        'routes' => $newRoutes,
                    ]);

                $response->assertOk();
                $response->assertJsonPath('data.routes', $newRoutes);

                expect($trunk->fresh()->routes)->toEqual($newRoutes);
            });
        });

        describe('Delete', function () {
            it('deletes a trunk and returns 204', function () {
                $trunk = Trunk::factory()->create(['name' => 'To Delete']);

                $response = $this->actingAs($this->user, 'tenant-api')
                    ->deleteJson($this->tenantApiUrl("ai/trunks/{$trunk->id}"));

                $response->assertNoContent();
                expect(Trunk::query()->whereKey($trunk->id)->exists())->toBeFalse();
            });
        });
    });
});
