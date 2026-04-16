<?php

use App\Models\Tenant\Agent\Agent;
use Illuminate\Support\Facades\Http;

describe('Agent Test Call', function () {
    describe('Start', function () {
        it('requires authentication', function () {
            $agent = Agent::factory()->create();

            $response = $this->postJson(
                $this->tenantApiUrl("ai/agents/{$agent->id}/test-call")
            );

            $response->assertUnauthorized();
        });

        it('creates a livekit session through the runner', function () {
            $agent = Agent::factory()->create();

            Http::fake([
                '*/api/v1/sessions/*' => Http::response([
                    'session_id' => 'sess_123',
                    'room_name' => 'room_abc',
                    'provider' => 'livekit',
                    'url' => 'wss://example.livekit.cloud',
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

        it('aborts when runner returns an unsupported transport', function () {
            $agent = Agent::factory()->create();

            Http::fake([
                '*/api/v1/sessions/*' => Http::sequence()
                    ->push([
                        'session_id' => 'sess_999',
                        'room_name' => '',
                        'provider' => 'daily',
                        'url' => '',
                        'access_token' => '',
                        'context' => [],
                    ], 201)
                    ->push(['ok' => true], 200),
            ]);

            $response = $this->actingAs($this->user, 'tenant-api')
                ->postJson($this->tenantApiUrl("ai/agents/{$agent->id}/test-call"));

            $response->assertStatus(503);
        });
    });

    describe('Stop', function () {
        it('terminates a session', function () {
            $agent = Agent::factory()->create();

            Http::fake([
                '*/api/v1/sessions/sess_terminate' => Http::response(['ok' => true], 200),
            ]);

            $response = $this->actingAs($this->user, 'tenant-api')
                ->deleteJson(
                    $this->tenantApiUrl("ai/agents/{$agent->id}/test-call/sess_terminate")
                );

            $response->assertOk();
            $response->assertJsonPath('data.terminated', true);
        });
    });
});
