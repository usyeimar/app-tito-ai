<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Auth;

use App\Policies\ResourcesPolicy;
use App\Policies\ServicePolicy;
use App\Support\Permissions\TenantPermissionRegistry;
use PHPUnit\Framework\TestCase;

class ResourcesPermissionConsolidationTest extends TestCase
{
    public function test_it_registers_resources_module_and_removes_legacy_resource_modules(): void
    {
        $moduleKeys = array_map(
            static fn (array $module): string => $module['key'],
            TenantPermissionRegistry::modules(),
        );

        $this->assertContains('resources', $moduleKeys);
        $this->assertNotContains('equipment', $moduleKeys);
        $this->assertNotContains('vehicle', $moduleKeys);
        $this->assertNotContains('material', $moduleKeys);
        $this->assertNotContains('service_related_resource', $moduleKeys);
    }

    public function test_resources_and_service_policies_bind_expected_module_keys(): void
    {
        $resourcesPolicy = new ResourcesPolicy;
        $servicePolicy = new ServicePolicy;

        $readModule = static function (object $policy): string {
            foreach ((array) $policy as $key => $value) {
                if (str_ends_with((string) $key, 'module')) {
                    return (string) $value;
                }
            }

            return '';
        };

        $this->assertSame('resources', $readModule($resourcesPolicy));
        $this->assertSame('services', $readModule($servicePolicy));
    }
}
