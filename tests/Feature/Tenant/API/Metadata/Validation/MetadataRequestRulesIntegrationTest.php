<?php

use App\Enums\ModuleType;
use App\Http\Requests\Tenant\Resources\Vehicle\StoreVehicleRequest;
use App\Models\Tenant\Metadata\ResourceType\ResourceType;
use App\Models\Tenant\Metadata\Tag\Tag;
use Illuminate\Support\Facades\Validator;

it('validates vehicle type against metadata_resource_types with module scoping', function (): void {
    $validType = ResourceType::factory()->forModule(ModuleType::VEHICLES)->create();
    $wrongType = ResourceType::factory()->forModule(ModuleType::EQUIPMENT)->create();
    $tag = Tag::factory()->forModule(ModuleType::VEHICLES)->create();

    $validPayload = [
        'name' => 'Test Vehicle',
        'type_id' => $validType->id,
        'tag_ids' => [$tag->id],
    ];

    $validValidator = Validator::make($validPayload, (new StoreVehicleRequest)->rules());

    expect($validValidator->passes())->toBeTrue($validValidator->errors()->toJson());

    $invalidPayload = [
        'name' => 'Test Vehicle',
        'type_id' => $wrongType->id,
    ];

    $invalidValidator = Validator::make($invalidPayload, (new StoreVehicleRequest)->rules());

    expect($invalidValidator->fails())->toBeTrue();
    expect($invalidValidator->errors()->keys())->toContain('type_id');
});
