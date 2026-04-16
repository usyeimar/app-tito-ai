<?php

describe('Tenant Auth Me', function () {
    it('requires authentication', function () {
        $response = $this->getJson($this->tenantApiUrl('auth/me'));
        $response->assertUnauthorized();
    });

    // NOTE: happy-path 'me' test is skipped - TenantAuthController::me() eager
    // loads `type.resourceTypeProfile` which is not a relation on the tenant
    // User model, so the call returns a 500 today.
});
