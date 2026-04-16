<?php

use App\Models\Tenant\Agent\Agent;

describe('Agent Pages (Inertia)', function () {
    describe('Index', function () {
        it('redirects guests to login', function () {
            $response = $this->get('/'.$this->tenant->slug.'/agents');
            $response->assertRedirect();
        });

        it('renders the agents index page', function () {
            $response = $this->actingAs($this->user, 'tenant')
                ->get('/'.$this->tenant->slug.'/agents');

            $response->assertOk();
            $response->assertInertia(
                fn ($page) => $page
                    ->component('tenant/agents/show')
                    ->where('agent', null)
                    ->has('agents')
                    ->has('tenant.id')
            );
        });

    });

    describe('Show', function () {
        it('renders the agent detail page', function () {
            $agent = Agent::factory()->create();

            $response = $this->actingAs($this->user, 'tenant')
                ->get('/'.$this->tenant->slug.'/agents/'.$agent->id);

            $response->assertOk();
            $response->assertInertia(
                fn ($page) => $page
                    ->component('tenant/agents/show')
                    ->where('agent.id', (string) $agent->id)
                    ->has('agents')
            );
        });

        it('returns 404 for an unknown agent', function () {
            $response = $this->actingAs($this->user, 'tenant')
                ->get('/'.$this->tenant->slug.'/agents/01HX99999999999999999999999');

            $response->assertNotFound();
        });
    });
});
