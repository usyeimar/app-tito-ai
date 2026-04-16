<?php

describe('Knowledge Base Pages (Inertia)', function () {
    it('redirects guests to login', function () {
        $response = $this->get('/'.$this->tenant->slug.'/knowledge');
        $response->assertRedirect();
    });

    it('renders the knowledge base index page', function () {
        $response = $this->actingAs($this->user, 'tenant')
            ->get('/'.$this->tenant->slug.'/knowledge');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('tenant/knowledge/index')
                ->has('tenant.id')
                ->has('documents')
        );
    });
});
