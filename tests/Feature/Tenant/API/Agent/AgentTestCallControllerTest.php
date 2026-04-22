<?php

use App\Models\Tenant\Agent\Agent;
use App\Services\Tenant\Agent\Runner\RunnerCommandBus;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;

describe('Agent Test Call', function () {
    describe('Start', function () {
        it('requires authentication', function () {
            $agent = Agent::factory()->create();

            $response = $this->postJson(
                $this->tenantApiUrl("ai/agents/{$agent->id}/test-call")
            );

            $response->assertUnauthorized();
        });

        it('creates a livekit session via HTTP', function () {
            $agent = Agent::factory()->create();

            Http::fake([
                '*/api/v1/sessions/*' => Http::response([
                    'session_id' => 'sess_123',
                    'room_name' => 'room_abc',
                    'provider' => 'livekit',
                    'ws_url' => 'wss://example.livekit.cloud',
                    'access_token' => 'token-xyz',
                    'context' => [],
                ], 201),
            ]);

            $response = $this->actingAs($this->user, 'tenant-api')
                ->postJson($this->tenantApiUrl("ai/agents/{$agent->id}/test-call"));

            $response->assertCreated();
            $response->assertJsonPath('success', true);
            $response->assertJsonPath('data.session_id', 'sess_123');
            $response->assertJsonPath('data.provider', 'livekit');
        });

        it('creates a daily session via HTTP', function () {
            $agent = Agent::factory()->create();

            Http::fake([
                '*/api/v1/sessions/*' => Http::response([
                    'session_id' => 'sess_daily_456',
                    'room_name' => 'room_daily',
                    'provider' => 'daily',
                    'ws_url' => 'https://example.daily.co/room_daily',
                    'access_token' => 'daily-token-xyz',
                    'context' => [],
                ], 201),
            ]);

            $response = $this->actingAs($this->user, 'tenant-api')
                ->postJson($this->tenantApiUrl("ai/agents/{$agent->id}/test-call"));

            $response->assertCreated();
            $response->assertJsonPath('success', true);
            $response->assertJsonPath('data.session_id', 'sess_daily_456');
            $response->assertJsonPath('data.provider', 'daily');
        });

        it('aborts when runner returns an unsupported transport', function () {
            $agent = Agent::factory()->create();

            Http::fake([
                '*/api/v1/sessions/*' => Http::response([
                    'session_id' => 'sess_999',
                    'room_name' => '',
                    'provider' => 'twilio',
                    'ws_url' => '',
                    'access_token' => '',
                    'context' => [],
                ], 201),
            ]);

            $this->mock(RunnerCommandBus::class, function (MockInterface $mock) {
                $mock->shouldReceive('dispatch')->once();
            });

            $response = $this->actingAs($this->user, 'tenant-api')
                ->postJson($this->tenantApiUrl("ai/agents/{$agent->id}/test-call"));

            $response->assertStatus(503);
        });
    });

    describe('Stop', function () {
        it('terminates a session via Redis', function () {
            $agent = Agent::factory()->create();

            $this->mock(RunnerCommandBus::class, function (MockInterface $mock) {
                $mock->shouldReceive('dispatch')
                    ->with('session.terminate', Mockery::on(
                        fn ($p) => $p['session_id'] === 'sess_terminate'
                    ))
                    ->once();
            });

            $response = $this->actingAs($this->user, 'tenant-api')
                ->deleteJson(
                    $this->tenantApiUrl("ai/agents/{$agent->id}/test-call/sess_terminate")
                );

            $response->assertOk();
            $response->assertJsonPath('data.terminated', true);
        });
    });
});
