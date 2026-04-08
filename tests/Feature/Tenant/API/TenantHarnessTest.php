<?php

use App\Models\Tenant\Auth\Authentication\User;

it('creates a tenant with an authenticated super admin', function () {
    expect($this->tenant)->not->toBeNull();
    expect($this->user)->toBeInstanceOf(User::class);
    expect($this->user->hasRole('super_admin'))->toBeTrue();
});

it('can make authenticated API requests to tenant routes', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('metadata/statuses'));

    $response->assertOk();
});
