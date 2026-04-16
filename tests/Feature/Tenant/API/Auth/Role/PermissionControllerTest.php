<?php

use App\Models\Tenant\Auth\Authentication\User;

describe('Permissions API', function () {
    it('requires authentication', function () {
        $response = $this->getJson($this->tenantApiUrl('permissions'));
        $response->assertUnauthorized();
    });

    it('lists permission modules for super admin', function () {
        $response = $this->actingAs($this->user, 'tenant-api')
            ->getJson($this->tenantApiUrl('permissions'));

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['modules']]);
    });

    it('filters modules by search term', function () {
        $response = $this->actingAs($this->user, 'tenant-api')
            ->getJson($this->tenantApiUrl('permissions?filter[search]=role'));

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['modules']]);
    });

    it('denies access for user without role.manage permission', function () {
        $regularUser = User::factory()->create();

        $response = $this->actingAs($regularUser, 'tenant-api')
            ->getJson($this->tenantApiUrl('permissions'));

        $response->assertForbidden();
    });
});
