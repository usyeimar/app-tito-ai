<?php

use App\Data\Tenant\Metadata\ResourceType\ResourceTypeData;
use App\Enums\ModuleType;
use App\Models\Tenant\Commons\EntityProfilePicture;
use App\Models\Tenant\Metadata\ResourceType\ResourceType;

it('hydrates profile picture when using from with resource type model', function () {
    $resourceType = ResourceType::factory()
        ->forModule(ModuleType::VEHICLES)
        ->create();

    EntityProfilePicture::query()->create([
        'entity_type' => $resourceType->getMorphClass(),
        'entity_id' => $resourceType->getKey(),
        'path' => 'profile-images/resource_type/01HXYZ.webp',
    ]);

    $data = ResourceTypeData::from($resourceType);

    expect($data->profile_picture)
        ->toBeArray()
        ->toHaveKeys(['id', 'url']);
});

it('returns null profile picture when none set', function () {
    $resourceType = ResourceType::factory()
        ->forModule(ModuleType::VEHICLES)
        ->create();

    $data = ResourceTypeData::from($resourceType);

    expect($data->profile_picture)->toBeNull();
});
