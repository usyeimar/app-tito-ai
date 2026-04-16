<?php

describe('Notifications API', function () {
    it('requires authentication to list notifications', function () {
        $response = $this->getJson($this->tenantApiUrl('notifications'));
        $response->assertUnauthorized();
    });

    it('requires authentication to mark batch as read', function () {
        $response = $this->postJson($this->tenantApiUrl('notifications/batch/read'), [
            'ids' => ['some-id'],
        ]);
        $response->assertUnauthorized();
    });

    it('rejects empty batch read payload', function () {
        $response = $this->actingAs($this->user, 'tenant-api')
            ->postJson($this->tenantApiUrl('notifications/batch/read'), [
                'ids' => [],
            ]);

        $response->assertUnprocessable();
    });

    it('rejects empty batch delete payload', function () {
        $response = $this->actingAs($this->user, 'tenant-api')
            ->postJson($this->tenantApiUrl('notifications/batch/delete'), [
                'ids' => [],
            ]);

        $response->assertUnprocessable();
    });

    it('rejects too many ids in batch payload', function () {
        $response = $this->actingAs($this->user, 'tenant-api')
            ->postJson($this->tenantApiUrl('notifications/batch/delete'), [
                'ids' => array_map(fn ($i) => 'id-'.$i, range(1, 201)),
            ]);

        $response->assertUnprocessable();
    });

    // NOTE: integration tests for index/mark-as-read/destroy are skipped because
    // the tenant database does not currently have a `notifications` table
    // migration. Re-enable once notifications storage is provisioned per-tenant.
});
