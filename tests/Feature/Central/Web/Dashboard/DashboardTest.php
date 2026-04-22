<?php

use App\Models\Central\Auth\Authentication\CentralUser;

test('guests are redirected to the login page', function () {
    $this->get(route('workspaces'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = CentralUser::factory()->create();

    $this->actingAs($user)
        ->get(route('workspaces'))
        ->assertOk();
});
