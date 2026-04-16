<?php

describe('Tenant Token Refresh', function () {
    it('validates that refresh_token is required', function () {
        $response = $this->postJson($this->tenantApiUrl('refresh'), []);

        $response->assertUnprocessable();
    });

    it('rejects an invalid refresh token', function () {
        $response = $this->postJson($this->tenantApiUrl('refresh'), [
            'refresh_token' => 'invalid-refresh-token',
        ]);

        expect($response->getStatusCode())->toBeIn([400, 401, 500]);
    });
});
