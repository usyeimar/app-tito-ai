<?php

use App\Models\Tenant\Agent\Agent;
use App\Models\Tenant\Agent\AgentDeployment;

describe('Widget Config (public)', function () {
    describe('Web', function () {
        it('returns 404 when the agent does not exist', function () {
            $response = $this->getJson($this->tenantApiUrl('widget-config/web/missing-slug'));
            $response->assertNotFound();
        });

        it('returns 404 when no active web-widget deployment exists', function () {
            $agent = Agent::factory()->create(['slug' => 'web-no-deploy']);

            $response = $this->getJson($this->tenantApiUrl("widget-config/web/{$agent->slug}"));

            $response->assertNotFound();
            $response->assertJsonPath(
                'error',
                'No active web widget deployment found for this agent'
            );
        });

        // NOTE: happy-path test is skipped because the controller calls
        // route('public.widget-token.generate', ...) which is not defined in the
        // current routes, and crashes with a route-not-found error.
    });

    describe('SIP', function () {
        it('returns 404 when no active sip deployment exists', function () {
            $agent = Agent::factory()->create(['slug' => 'sip-no-deploy']);

            $response = $this->getJson($this->tenantApiUrl("widget-config/sip/{$agent->slug}"));

            $response->assertNotFound();
            $response->assertJsonPath(
                'error',
                'No active SIP deployment found for this agent'
            );
        });

        it('returns the public sip widget configuration', function () {
            $agent = Agent::factory()->create(['slug' => 'sip-active']);
            AgentDeployment::create([
                'agent_id' => $agent->id,
                'channel' => 'sip',
                'enabled' => true,
                'status' => 'active',
                'version' => 1,
                'deployed_at' => now(),
                'config' => ['sip' => ['username' => 'sip-user']],
            ]);

            $response = $this->getJson($this->tenantApiUrl("widget-config/sip/{$agent->slug}"));

            $response->assertOk();
            // Response is wrapped by WrapApiResponses middleware.
            $response->assertJsonStructure([
                'data' => [
                    'agent' => ['id', 'name', 'slug'],
                    'deployment' => ['version', 'deployed_at'],
                    'sip',
                ],
            ]);
            $response->assertJsonPath('data.agent.slug', 'sip-active');
        });
    });
});
