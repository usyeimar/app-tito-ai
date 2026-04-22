<?php

use App\Models\Central\Auth\Authentication\CentralUser;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Cache;

test('confirmation status returns false when password not confirmed', function () {
    $user = CentralUser::factory()->create();

    $this->actingAs($user)
        ->getJson(route('me.confirmed-password-status'))
        ->assertOk()
        ->assertJson(['confirmed' => false]);
});

test('confirmation status returns true when password is confirmed', function () {
    $user = CentralUser::factory()->create();

    Cache::put("auth:confirmed:{$user->id}", true, now()->addMinutes(10));

    $this->actingAs($user)
        ->getJson(route('me.confirmed-password-status'))
        ->assertOk()
        ->assertJson(['confirmed' => true]);
});

test('confirm password succeeds with correct password', function () {
    $user = CentralUser::factory()->create();

    $this->withoutMiddleware(PreventRequestForgery::class);

    $this->actingAs($user)
        ->postJson(route('me.confirm-password'), [
            'password' => 'password',
        ])
        ->assertStatus(201)
        ->assertJson(['confirmed' => true]);

    expect(Cache::has("auth:confirmed:{$user->id}"))->toBeTrue();
});

test('confirm password fails with incorrect password', function () {
    $user = CentralUser::factory()->create();

    $this->withoutMiddleware(PreventRequestForgery::class);

    $this->actingAs($user)
        ->postJson(route('me.confirm-password'), [
            'password' => 'wrong-password',
        ])
        ->assertStatus(422);
});

test('confirm password requires password field', function () {
    $user = CentralUser::factory()->create();

    $this->withoutMiddleware(PreventRequestForgery::class);

    $this->actingAs($user)
        ->postJson(route('me.confirm-password'), [])
        ->assertStatus(422);
});

test('confirm password requires authentication', function () {
    $this->withoutMiddleware(PreventRequestForgery::class);

    $this->post(route('me.confirm-password'), [
        'password' => 'password',
    ])->assertRedirect('/login');
});
