<?php

use App\Models\Central\Auth\Role\Permission;
use App\Models\Tenant\CRM\Leads\Lead;
use App\Models\Tenant\Metadata\CustomField\GlobalCustomFieldDefinition;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->object = Lead::getObjectMetadata()['name_singular'];
});

it('allows listing fields with metadata.view permission', function () {
    GlobalCustomFieldDefinition::factory()
        ->forEntity(Lead::class)
        ->create([
            'name' => 'test_'.Str::lower(Str::random(8)),
            'label' => 'Test Field',
            'type' => 'TEXT',
            'is_required' => false,
            'position' => 1,
        ]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("metadata/objects/{$this->object}/fields"));

    $response->assertOk();
});

it('denies listing fields without metadata.view permission', function () {
    $viewer = $this->createTenantUser();

    foreach (['metadata.view', 'metadata.manage', 'metadata.delete'] as $permissionName) {
        $permissionModel = Permission::query()->firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'tenant',
        ]);

        $viewer->givePermissionTo($permissionModel);
    }

    $viewer->revokePermissionTo('metadata.view');

    $response = $this->actingAs($viewer, 'tenant-api')
        ->getJson($this->tenantApiUrl("metadata/objects/{$this->object}/fields"));

    $response->assertForbidden();
});

dataset('fields permission denials', [
    'show requires metadata.view' => [
        'permission' => 'metadata.view',
        'method' => 'getJson',
        'path' => 'metadata/objects/{object}/fields/{field}',
        'payload' => [],
        'requiresField' => true,
    ],
    'create requires metadata.manage' => [
        'permission' => 'metadata.manage',
        'method' => 'postJson',
        'path' => 'metadata/objects/{object}/fields',
        'payload' => [
            'label' => str_repeat('x', 300),
            'type' => 'INVALID',
            'options' => 'not-an-array',
        ],
        'requiresField' => false,
    ],
    'update requires metadata.manage' => [
        'permission' => 'metadata.manage',
        'method' => 'patchJson',
        'path' => 'metadata/objects/{object}/fields/{field}',
        'payload' => [
            'label' => ['invalid'],
            'options' => 'not-an-array',
            'position' => 'invalid-int',
        ],
        'requiresField' => true,
    ],
    'delete requires metadata.delete' => [
        'permission' => 'metadata.delete',
        'method' => 'deleteJson',
        'path' => 'metadata/objects/{object}/fields/{field}',
        'payload' => [],
        'requiresField' => true,
    ],
]);

it('denies field actions without required permission', function (string $permission, string $method, string $path, array $payload, bool $requiresField) {
    $viewer = $this->createTenantUser();

    foreach (['metadata.view', 'metadata.manage', 'metadata.delete'] as $permissionName) {
        $permissionModel = Permission::query()->firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'tenant',
        ]);

        $viewer->givePermissionTo($permissionModel);
    }

    $viewer->revokePermissionTo($permission);

    $fieldId = null;

    if ($requiresField) {
        $fieldId = GlobalCustomFieldDefinition::factory()
            ->forEntity(Lead::class)
            ->create([
                'name' => 'test_'.Str::lower(Str::random(8)),
                'label' => 'Test Field',
                'type' => 'TEXT',
                'is_required' => false,
                'position' => 1,
            ])->id;
    }

    $endpoint = str_replace('{object}', $this->object, $path);

    if ($fieldId) {
        $endpoint = str_replace('{field}', (string) $fieldId, $endpoint);
    }

    $url = $this->tenantApiUrl($endpoint);

    $response = match ($method) {
        'postJson', 'patchJson' => $this->actingAs($viewer, 'tenant-api')->{$method}($url, $payload),
        default => $this->actingAs($viewer, 'tenant-api')->{$method}($url),
    };

    $response->assertForbidden();
})->with('fields permission denials');
