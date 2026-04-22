<?php

use App\Models\User;

describe('Settings Pages', function () {
    describe('Appearance', function () {
        it('redirects guests to login', function () {
            $response = $this->get(route('appearance.edit'));
            $response->assertRedirect(route('login'));
        });

        it('renders the appearance settings page', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)
                ->get(route('appearance.edit'));

            $response->assertOk();
            $response->assertInertia(
                fn ($page) => $page->component('settings/appearance')
            );
        });
    });

    describe('Profile', function () {
        it('redirects guests to login', function () {
            $response = $this->get(route('profile.edit'));
            $response->assertRedirect(route('login'));
        });

        it('renders the profile settings page', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)
                ->get(route('profile.edit'));

            $response->assertOk();
            $response->assertInertia(
                fn ($page) => $page->component('settings/profile')
            );
        });
    });

    describe('Security', function () {
        it('redirects guests to login', function () {
            $response = $this->get(route('security.edit'));
            $response->assertRedirect(route('login'));
        });

        it('renders the security settings page', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)
                ->get(route('security.edit'));

            $response->assertOk();
            $response->assertInertia(
                fn ($page) => $page->component('settings/security')
            );
        });
    });
});
