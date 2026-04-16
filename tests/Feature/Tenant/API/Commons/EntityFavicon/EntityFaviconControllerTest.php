<?php

it('returns 404 when the favicon record does not exist', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->get($this->tenantApiUrl('entity-favicons/01HX99999999999999999999999'));

    $response->assertNotFound();
});

it('requires authentication', function () {
    $response = $this->getJson($this->tenantApiUrl('entity-favicons/01HX99999999999999999999999'));

    $response->assertUnauthorized();
});

// NOTE: end-to-end favicon download tests are skipped because the tenant DB
// does not currently include an `entity_favicons` migration. Re-enable once
// the table is provisioned per-tenant.
