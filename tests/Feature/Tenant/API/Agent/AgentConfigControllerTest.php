<?php

use App\Models\Tenant\Agent\Agent;

describe('Agent Config (runners)', function () {
    it('returns 404 when the agent id does not exist', function () {
        $response = $this->getJson(
            $this->tenantApiUrl('agents/01HX99999999999999999999999/config')
        );

        $response->assertNotFound();
        $response->assertJsonPath('error', 'Agent not found');
    });

    it('returns 404 when the agent slug does not exist', function () {
        $response = $this->getJson(
            $this->tenantApiUrl('agents/by-slug/non-existent-slug/config')
        );

        $response->assertNotFound();
        $response->assertJsonPath('error', 'Agent not found');
    });

    it('returns the agent configuration by id', function () {
        $agent = Agent::factory()->create();

        $response = $this->getJson(
            $this->tenantApiUrl("agents/{$agent->id}/config")
        );

        $response->assertOk();
    });

    it('returns the agent configuration by slug', function () {
        $agent = Agent::factory()->create(['slug' => 'config-test-agent']);

        $response = $this->getJson(
            $this->tenantApiUrl("agents/by-slug/{$agent->slug}/config")
        );

        $response->assertOk();
    });
});
