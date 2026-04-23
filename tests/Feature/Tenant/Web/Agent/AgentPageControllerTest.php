<?php

use App\Models\Tenant\Agent\Agent;

describe('Agent Pages (Inertia)', function () {
    describe('Authentication', function () {
        it('redirects guests to login on index', function () {
            $this->get('/'.$this->tenant->slug.'/agents')
                ->assertRedirect();
        });

        it('redirects guests to login on show', function () {
            $agent = Agent::factory()->create();

            $this->get('/'.$this->tenant->slug.'/agents/'.$agent->id)
                ->assertRedirect();
        });
    });

    describe('Index', function () {
        it('renders the agents index page', function () {
            $this->actingAs($this->user, 'tenant')
                ->get('/'.$this->tenant->slug.'/agents')
                ->assertOk()
                ->assertInertia(
                    fn ($page) => $page
                        ->component('tenant/agents/show')
                        ->where('agent', null)
                        ->has('tenant.id')
                );
        });
    });

    describe('Show', function () {
        it('renders the agent detail page', function () {
            $agent = Agent::factory()->create();

            $this->actingAs($this->user, 'tenant')
                ->get('/'.$this->tenant->slug.'/agents/'.$agent->id)
                ->assertOk()
                ->assertInertia(
                    fn ($page) => $page
                        ->component('tenant/agents/show')
                        ->where('agent.id', (string) $agent->id)
                        ->has('tenant.id')
                );
        });

        it('returns 404 for an unknown agent', function () {
            $this->actingAs($this->user, 'tenant')
                ->get('/'.$this->tenant->slug.'/agents/01HX99999999999999999999999')
                ->assertNotFound();
        });
    });
});
