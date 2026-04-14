<?php

use App\Models\Central\Tenancy\TenantInvitation;
use App\Models\Tenant\Auth\Authentication\User;

describe('Invitations API', function () {
    describe('Index', function () {
        it('requires authentication', function () {
            $response = $this->getJson($this->tenantApiUrl('invitations'));
            $response->assertUnauthorized();
        });

        it('lists invitations for tenant', function () {
            TenantInvitation::create([
                'tenant_id' => $this->tenant->id,
                'email' => 'newuser@example.com',
                'token_hash' => hash('sha256', 'test-token'),
                'invited_by_central_user_id' => null,
                'status' => TenantInvitation::STATUS_PENDING,
                'expires_at' => now()->addDays(7),
            ]);

            $response = $this->actingAs($this->user, 'tenant-api')
                ->getJson($this->tenantApiUrl('invitations'));

            $response->assertOk();
        });

        it('denies access for user without invitation permissions', function () {
            $regularUser = User::factory()->create();

            $response = $this->actingAs($regularUser, 'tenant-api')
                ->getJson($this->tenantApiUrl('invitations'));

            $response->assertForbidden();
        });
    });

    describe('Revoke', function () {
        it('revokes a pending invitation', function () {
            $invitation = TenantInvitation::create([
                'tenant_id' => $this->tenant->id,
                'email' => 'user'.uniqid().'@example.com',
                'token_hash' => hash('sha256', 'test-token-'.uniqid()),
                'invited_by_central_user_id' => null,
                'status' => TenantInvitation::STATUS_PENDING,
                'expires_at' => now()->addDays(7),
            ]);

            $response = $this->actingAs($this->user, 'tenant-api')
                ->deleteJson($this->tenantApiUrl("invitations/{$invitation->id}"));

            $response->assertOk();
            expect($invitation->fresh()->status)->toBe(TenantInvitation::STATUS_REVOKED);
        });

        it('cannot revoke non-pending invitation', function () {
            $invitation = TenantInvitation::create([
                'tenant_id' => $this->tenant->id,
                'email' => 'another'.uniqid().'@example.com',
                'token_hash' => hash('sha256', 'another-token-'.uniqid()),
                'invited_by_central_user_id' => null,
                'status' => TenantInvitation::STATUS_ACCEPTED,
                'expires_at' => now()->addDays(7),
            ]);

            $response = $this->actingAs($this->user, 'tenant-api')
                ->deleteJson($this->tenantApiUrl("invitations/{$invitation->id}"));

            $response->assertUnprocessable();
        });
    });
});
