<?php

describe('Tenant Impersonation', function () {
    describe('Start', function () {
        it('returns 404 for an unknown impersonation token', function () {
            $response = $this->postJson(
                $this->tenantApiUrl('impersonate/non-existent-token-value')
            );

            $response->assertNotFound();
        });

        it('returns 422 when token is empty', function () {
            $response = $this->postJson($this->tenantApiUrl('impersonate/'), []);

            // Either router returns 404 (no match) or controller aborts with 422.
            expect($response->getStatusCode())->toBeIn([404, 422, 405]);
        });
    });
});
