<?php

describe('Activity API', function () {
    it('requires authentication', function () {
        $response = $this->getJson($this->tenantApiUrl('activity'));
        $response->assertUnauthorized();
    });

    it('rejects requests without the required subject filters', function () {
        $response = $this->actingAs($this->user, 'tenant-api')
            ->getJson($this->tenantApiUrl('activity'));

        $response->assertUnprocessable();
        assertHasValidationError($response, 'filter.subject_type');
        assertHasValidationError($response, 'filter.subject_id');
    });
});
