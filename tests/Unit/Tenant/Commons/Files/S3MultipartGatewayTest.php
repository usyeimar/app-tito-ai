<?php

namespace Tests\Unit\Tenant\Commons\Files;

use App\Services\Tenant\Commons\Files\Uploads\S3MultipartGateway;
use ReflectionMethod;
use Tests\TestCase;

class S3MultipartGatewayTest extends TestCase
{
    public function test_it_prefers_explicit_presign_endpoint_when_configured(): void
    {
        config()->set('app.url', 'https://app.tito.ai:4443');
        $this->app->detectEnvironment(fn (): string => 'local');

        $endpoint = $this->resolvePresignEndpoint([
            'presign_endpoint' => 'https://uploads.workupcloud.test:4443',
            'url' => 'http://rustfs:9000/workupcloud-local',
            'endpoint' => 'http://rustfs:9000',
        ]);

        $this->assertSame('https://uploads.workupcloud.test:4443', $endpoint);
    }

    public function test_it_uses_application_url_for_internal_url_host_in_local_environment(): void
    {
        config()->set('app.url', 'https://app.tito.ai:4443');
        $this->app->detectEnvironment(fn (): string => 'local');

        $endpoint = $this->resolvePresignEndpoint([
            'url' => 'http://rustfs:9000/tito-local',
            'endpoint' => 'http://rustfs:9000',
        ]);

        $this->assertSame('https://app.tito.ai:4443', $endpoint);
    }

    public function test_it_does_not_rewrite_internal_url_host_outside_local_environment(): void
    {
        config()->set('app.url', 'https://app.tito.ai:4443');
        $this->app->detectEnvironment(fn (): string => 'staging');

        $endpoint = $this->resolvePresignEndpoint([
            'url' => 'http://rustfs:9000/tito-local',
            'endpoint' => 'http://rustfs:9000',
        ]);

        $this->assertSame('http://rustfs:9000', $endpoint);
    }

    public function test_it_keeps_localhost_url_in_local_environment(): void
    {
        config()->set('app.url', 'https://app.tito.ai:4443');
        $this->app->detectEnvironment(fn (): string => 'local');

        $endpoint = $this->resolvePresignEndpoint([
            'url' => 'http://localhost:9010/tito-local',
            'endpoint' => 'http://rustfs:9000',
        ]);

        $this->assertSame('http://localhost:9010', $endpoint);
    }

    public function test_it_prefixes_provider_object_key_with_tenant_root_when_present(): void
    {
        config()->set('filesystems.disks.s3.root', 'tenant_01abc');

        $providerKey = $this->resolveProviderObjectKey('s3', 'files/contact/abc/file.jpg');

        $this->assertSame('tenant_01abc/files/contact/abc/file.jpg', $providerKey);
    }

    public function test_it_does_not_double_prefix_provider_object_key_when_already_prefixed(): void
    {
        config()->set('filesystems.disks.s3.root', 'tenant_01abc');

        $providerKey = $this->resolveProviderObjectKey('s3', 'tenant_01abc/files/contact/abc/file.jpg');

        $this->assertSame('tenant_01abc/files/contact/abc/file.jpg', $providerKey);
    }

    public function test_it_keeps_provider_object_key_unchanged_when_root_is_missing(): void
    {
        config()->set('filesystems.disks.s3.root', null);

        $providerKey = $this->resolveProviderObjectKey('s3', 'files/contact/abc/file.jpg');

        $this->assertSame('files/contact/abc/file.jpg', $providerKey);
    }

    /**
     * @param array<string, mixed> $config
     * @throws \ReflectionException
     */
    private function resolvePresignEndpoint(array $config): ?string
    {
        $gateway = new S3MultipartGateway;
        $method = new ReflectionMethod($gateway, 'presignEndpoint');
        $method->setAccessible(true);

        return $method->invoke($gateway, $config);
    }

    private function resolveProviderObjectKey(string $disk, string $objectKey): string
    {
        $gateway = new S3MultipartGateway;
        $method = new ReflectionMethod($gateway, 'providerObjectKey');
        $method->setAccessible(true);

        return $method->invoke($gateway, $disk, $objectKey);
    }
}
