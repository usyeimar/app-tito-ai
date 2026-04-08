<?php

namespace Tests\Unit\Central\Support\OutboundEmails;

use App\Services\Central\Support\OutboundEmails\ResendWebhookRoutingService;
use Tests\TestCase;

class ResendWebhookRoutingServiceTest extends TestCase
{
    public function test_it_extracts_tenant_routing_from_string_tags(): void
    {
        $service = new ResendWebhookRoutingService;

        $routing = $service->extractTenantRouting([
            'data' => [
                'tags' => [
                    'tenant_id:tenant_ulid_1',
                    'tenant_slug:demo',
                ],
            ],
        ]);

        $this->assertSame('tenant_ulid_1', $routing['tenant_id']);
        $this->assertSame('demo', $routing['tenant_slug']);
    }

    public function test_it_extracts_tenant_routing_from_key_value_tags(): void
    {
        $service = new ResendWebhookRoutingService;

        $routing = $service->extractTenantRouting([
            'data' => [
                'tags' => [
                    ['name' => 'tenant_id', 'value' => 'tenant_ulid_2'],
                    ['name' => 'tenant_slug', 'value' => 'acme'],
                ],
            ],
        ]);

        $this->assertSame('tenant_ulid_2', $routing['tenant_id']);
        $this->assertSame('acme', $routing['tenant_slug']);
    }

    public function test_it_extracts_tenant_routing_from_tag_map(): void
    {
        $service = new ResendWebhookRoutingService;

        $routing = $service->extractTenantRouting([
            'data' => [
                'tags' => [
                    'tenant_id' => 'tenant_ulid_3',
                    'tenant_slug' => 'workup',
                ],
            ],
        ]);

        $this->assertSame('tenant_ulid_3', $routing['tenant_id']);
        $this->assertSame('workup', $routing['tenant_slug']);
    }
}
