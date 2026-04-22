<?php

use App\Models\User;

describe('Workspaces Page', function () {
    describe('Authentication', function () {
        it('redirects guests to login', function () {
            $response = $this->get(route('workspaces'));
            $response->assertRedirect(route('login'));
        });
    });

    describe('Index', function () {
        it('renders the workspaces page for authenticated users', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)
                ->get(route('workspaces'));

            $response->assertOk();
            $response->assertInertia(
                fn ($page) => $page
                    ->component('workspaces/index')
                    ->has('workspaces')
                    ->has('appUrl')
                    ->has('invitations')
            );
        });
    });

    describe('Create', function () {
        it('creates a new workspace', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)
                ->post(route('workspaces.store'), [
                    'name' => 'My Workspace',
                ]);

            $response->assertRedirect(route('workspaces'));
        });
    });
});
