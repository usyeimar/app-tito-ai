<?php

use App\Enums\ModuleType;
use App\Models\Tenant\Metadata\Category\Category;
use App\Models\Tenant\Metadata\Industry\Industry;
use App\Models\Tenant\Metadata\LicenseType\LicenseType;
use App\Models\Tenant\Metadata\ResourceType\ResourceType;
use App\Models\Tenant\Metadata\Source\Source;
use App\Models\Tenant\Metadata\Tag\Tag;
use App\Models\Tenant\Metadata\Type\Type;
use Carbon\CarbonImmutable;

function metadataIndex(object $test, mixed $user, string $endpoint, array $query = [])
{
    $url = (fn (string $path): string => $this->tenantApiUrl($path))->call($test, $endpoint);

    if ($query !== []) {
        $url .= '?'.http_build_query($query);
    }

    return $test->actingAs($user, 'tenant-api')->getJson($url);
}

dataset('module_type_metadata_contracts', [
    'types' => [Type::class, 'metadata/types', ModuleType::CONTACTS, ModuleType::COMPANIES],
    'resource_types' => [ResourceType::class, 'metadata/resource-types', ModuleType::VEHICLES, ModuleType::EQUIPMENT],
    'tags' => [Tag::class, 'metadata/tags', ModuleType::LEADS, ModuleType::COMPANIES],
    'sources' => [Source::class, 'metadata/sources', ModuleType::LEADS, ModuleType::CONTACTS],
    'license_types' => [LicenseType::class, 'metadata/license-types', ModuleType::CONTACTS, ModuleType::COMPANIES],
    'categories' => [Category::class, 'metadata/categories', ModuleType::COMPANIES, ModuleType::SERVICES],
]);

it('supports text operators for name in module-type metadata', function (
    string $modelClass,
    string $endpoint,
    ModuleType $moduleA,
): void {
    $modelClass::factory()->forModule($moduleA)->create(['name' => 'Alpha Entry']);
    $modelClass::factory()->forModule($moduleA)->create(['name' => 'Beta Record']);
    $modelClass::factory()->forModule($moduleA)->create(['name' => '']);

    $cases = [
        'contains' => ['alp', ['Alpha Entry']],
        'does_not_contain' => ['!alp', ['Beta Record', '']],
        'starts_with' => ['starts(alp)', ['Alpha Entry']],
        'ends_with' => ['ends(record)', ['Beta Record']],
        'empty' => ['empty()', ['']],
        'not_empty' => ['!empty()', ['Alpha Entry', 'Beta Record']],
    ];

    foreach ($cases as [$filterValue, $expectedNames]) {
        $response = metadataIndex($this, $this->user, $endpoint, [
            'filter' => [
                'module_type' => $moduleA->value,
                'name' => $filterValue,
            ],
            'page' => ['size' => 100],
        ]);

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        expect($names)->toEqualCanonicalizing($expectedNames);
    }
})->with('module_type_metadata_contracts');

it('supports text operators for slug in module-type metadata', function (
    string $modelClass,
    string $endpoint,
    ModuleType $moduleA,
): void {
    $modelClass::factory()->forModule($moduleA)->create(['name' => 'Alpha Entry', 'slug' => 'alpha-entry']);
    $modelClass::factory()->forModule($moduleA)->create(['name' => 'Beta Record', 'slug' => 'beta-record']);
    $modelClass::factory()->forModule($moduleA)->create(['name' => 'No Slug', 'slug' => '']);

    $cases = [
        'contains' => ['alpha', ['alpha-entry']],
        'does_not_contain' => ['!alpha', ['beta-record', 'no-slug']],
        'starts_with' => ['starts(alpha)', ['alpha-entry']],
        'ends_with' => ['ends(record)', ['beta-record']],
        'empty' => ['empty()', []],
        'not_empty' => ['!empty()', ['alpha-entry', 'beta-record', 'no-slug']],
    ];

    foreach ($cases as [$filterValue, $expectedSlugs]) {
        $response = metadataIndex($this, $this->user, $endpoint, [
            'filter' => [
                'module_type' => $moduleA->value,
                'slug' => $filterValue,
            ],
            'page' => ['size' => 100],
        ]);

        $response->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug')->all();
        expect($slugs)->toEqualCanonicalizing($expectedSlugs);
    }
})->with('module_type_metadata_contracts');

it('supports set operators for module_type in module-type metadata', function (
    string $modelClass,
    string $endpoint,
    ModuleType $moduleA,
    ModuleType $moduleB,
): void {
    $modelClass::factory()->forModule($moduleA)->create(['name' => 'Module A']);
    $modelClass::factory()->forModule($moduleB)->create(['name' => 'Module B']);

    $cases = [
        'eq' => [$moduleA->value, [$moduleA->value]],
        'ne' => ['!'.$moduleA->value, [$moduleB->value]],
        'in' => [$moduleA->value.','.$moduleB->value, [$moduleA->value, $moduleB->value]],
        'not_in' => ['!'.$moduleA->value, [$moduleB->value]],
        'all_in' => ['all('.$moduleA->value.','.$moduleB->value.')', [$moduleA->value, $moduleB->value]],
        'not_all_in' => ['!all('.$moduleA->value.')', [$moduleB->value]],
    ];

    foreach ($cases as [$filterValue, $expectedValues]) {
        $response = metadataIndex($this, $this->user, $endpoint, [
            'filter' => [
                'module_type' => $filterValue,
            ],
            'page' => ['size' => 100],
        ]);

        $response->assertOk();
        $moduleValues = collect($response->json('data'))->pluck('module_type')->unique()->values()->all();
        expect($moduleValues)->toEqualCanonicalizing($expectedValues);
    }
})->with('module_type_metadata_contracts');

it('supports boolean operators for is_active in module-type metadata', function (
    string $modelClass,
    string $endpoint,
    ModuleType $moduleA,
): void {
    $modelClass::factory()->forModule($moduleA)->create(['name' => 'Active', 'is_active' => true]);
    $modelClass::factory()->forModule($moduleA)->create(['name' => 'Inactive', 'is_active' => false]);

    $cases = [
        'eq' => ['true', 1],
        'ne' => ['!true', 1],
        'empty' => ['empty()', 0],
        'not_empty' => ['!empty()', 2],
    ];

    foreach ($cases as [$filterValue, $expectedCount]) {
        $response = metadataIndex($this, $this->user, $endpoint, [
            'filter' => [
                'module_type' => $moduleA->value,
                'is_active' => $filterValue,
            ],
            'page' => ['size' => 100],
        ]);

        $response->assertOk();
        $response->assertJsonCount($expectedCount, 'data');
    }
})->with('module_type_metadata_contracts');

it('supports date operators for created_at in module-type metadata', function (
    string $modelClass,
    string $endpoint,
    ModuleType $moduleA,
): void {
    $dateA = CarbonImmutable::parse('2025-01-01 00:00:00');
    $dateB = CarbonImmutable::parse('2025-01-10 00:00:00');
    $dateC = CarbonImmutable::parse('2025-01-20 00:00:00');

    $modelClass::factory()->forModule($moduleA)->create(['name' => 'Created A', 'created_at' => $dateA, 'updated_at' => $dateA]);
    $modelClass::factory()->forModule($moduleA)->create(['name' => 'Created B', 'created_at' => $dateB, 'updated_at' => $dateB]);
    $modelClass::factory()->forModule($moduleA)->create(['name' => 'Created C', 'created_at' => $dateC, 'updated_at' => $dateC]);

    $cases = [
        'eq' => ['2025-01-10 00:00:00', ['Created B']],
        'eq_date_only' => ['2025-01-10', ['Created B']],
        'ne' => ['!2025-01-10 00:00:00', ['Created A', 'Created C']],
        'gt' => ['>2025-01-10 00:00:00', ['Created C']],
        'gte' => ['>=2025-01-10 00:00:00', ['Created B', 'Created C']],
        'lt' => ['<2025-01-10 00:00:00', ['Created A']],
        'lte' => ['<=2025-01-10 00:00:00', ['Created A', 'Created B']],
        'between' => ['2025-01-01 00:00:00..2025-01-10 00:00:00', ['Created A', 'Created B']],
        'empty' => ['empty()', []],
        'not_empty' => ['!empty()', ['Created A', 'Created B', 'Created C']],
    ];

    foreach ($cases as [$filterValue, $expectedNames]) {
        $response = metadataIndex($this, $this->user, $endpoint, [
            'filter' => [
                'module_type' => $moduleA->value,
                'created_at' => $filterValue,
            ],
            'page' => ['size' => 100],
        ]);

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        expect($names)->toEqualCanonicalizing($expectedNames);
    }
})->with('module_type_metadata_contracts');

it('supports date operators for updated_at in module-type metadata', function (
    string $modelClass,
    string $endpoint,
    ModuleType $moduleA,
): void {
    $createdAt = CarbonImmutable::parse('2025-01-01 00:00:00');

    $modelClass::factory()->forModule($moduleA)->create(['name' => 'Updated A', 'created_at' => $createdAt, 'updated_at' => CarbonImmutable::parse('2025-01-01 00:00:00')]);
    $modelClass::factory()->forModule($moduleA)->create(['name' => 'Updated B', 'created_at' => $createdAt, 'updated_at' => CarbonImmutable::parse('2025-01-10 00:00:00')]);
    $modelClass::factory()->forModule($moduleA)->create(['name' => 'Updated C', 'created_at' => $createdAt, 'updated_at' => CarbonImmutable::parse('2025-01-20 00:00:00')]);

    $cases = [
        'eq' => ['2025-01-10 00:00:00', ['Updated B']],
        'eq_date_only' => ['2025-01-10', ['Updated B']],
        'ne' => ['!2025-01-10 00:00:00', ['Updated A', 'Updated C']],
        'gt' => ['>2025-01-10 00:00:00', ['Updated C']],
        'gte' => ['>=2025-01-10 00:00:00', ['Updated B', 'Updated C']],
        'lt' => ['<2025-01-10 00:00:00', ['Updated A']],
        'lte' => ['<=2025-01-10 00:00:00', ['Updated A', 'Updated B']],
        'between' => ['2025-01-01 00:00:00..2025-01-10 00:00:00', ['Updated A', 'Updated B']],
        'empty' => ['empty()', []],
        'not_empty' => ['!empty()', ['Updated A', 'Updated B', 'Updated C']],
    ];

    foreach ($cases as [$filterValue, $expectedNames]) {
        $response = metadataIndex($this, $this->user, $endpoint, [
            'filter' => [
                'module_type' => $moduleA->value,
                'updated_at' => $filterValue,
            ],
            'page' => ['size' => 100],
        ]);

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        expect($names)->toEqualCanonicalizing($expectedNames);
    }
})->with('module_type_metadata_contracts');

it('rejects unsupported operators in module-type metadata', function (
    string $modelClass,
    string $endpoint,
    ModuleType $moduleA,
): void {
    $modelClass::factory()->forModule($moduleA)->create();

    $queries = [
        ['module_type' => '>invalid'],
        ['module_type' => 'invalid..other'],
        ['is_active' => 'true,false'],
    ];

    foreach ($queries as $filter) {
        $response = metadataIndex($this, $this->user, $endpoint, [
            'filter' => $filter,
        ]);

        $response->assertUnprocessable();
    }
})->with('module_type_metadata_contracts');

it('supports allowed sorting in module-type metadata', function (
    string $modelClass,
    string $endpoint,
    ModuleType $moduleA,
): void {
    $modelClass::factory()->forModule($moduleA)->create([
        'name' => 'Zulu',
        'is_active' => true,
        'position' => 2,
        'created_at' => CarbonImmutable::parse('2025-01-20 00:00:00'),
        'updated_at' => CarbonImmutable::parse('2025-01-20 00:00:00'),
    ]);

    $modelClass::factory()->forModule($moduleA)->create([
        'name' => 'Alpha',
        'is_active' => false,
        'position' => 1,
        'created_at' => CarbonImmutable::parse('2025-01-10 00:00:00'),
        'updated_at' => CarbonImmutable::parse('2025-01-10 00:00:00'),
    ]);

    $response = metadataIndex($this, $this->user, $endpoint, [
        'filter' => ['module_type' => $moduleA->value],
        'sort' => '+name,+module_type,+is_active,+position,+created_at,+updated_at',
        'page' => ['size' => 100],
    ]);

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name')->all();
    expect($names)->toEqual(['Alpha', 'Zulu']);
})->with('module_type_metadata_contracts');

it('rejects sorting by slug in module-type metadata', function (
    string $modelClass,
    string $endpoint,
    ModuleType $moduleA,
): void {
    $modelClass::factory()->forModule($moduleA)->create();

    $response = metadataIndex($this, $this->user, $endpoint, [
        'sort' => '+slug',
    ]);

    $response->assertUnprocessable();
})->with('module_type_metadata_contracts');

it('supports text operators for name in industries', function (): void {
    $categoryA = Category::factory()->forModule(ModuleType::COMPANIES)->create(['name' => 'Category A']);

    Industry::factory()->forCategory($categoryA)->create(['name' => 'Alpha Industry']);
    Industry::factory()->forCategory($categoryA)->create(['name' => 'Beta Sector']);
    Industry::factory()->forCategory($categoryA)->create(['name' => '']);

    $cases = [
        'contains' => ['alp', ['Alpha Industry']],
        'does_not_contain' => ['!alp', ['Beta Sector', '']],
        'starts_with' => ['starts(alp)', ['Alpha Industry']],
        'ends_with' => ['ends(sector)', ['Beta Sector']],
        'empty' => ['empty()', ['']],
        'not_empty' => ['!empty()', ['Alpha Industry', 'Beta Sector']],
    ];

    foreach ($cases as [$filterValue, $expectedNames]) {
        $response = metadataIndex($this, $this->user, 'metadata/industries', [
            'filter' => [
                'category_id' => $categoryA->id,
                'name' => $filterValue,
            ],
            'page' => ['size' => 100],
        ]);

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        expect($names)->toEqualCanonicalizing($expectedNames);
    }
});

it('supports text operators for slug in industries', function (): void {
    $categoryA = Category::factory()->forModule(ModuleType::COMPANIES)->create(['name' => 'Category A']);

    Industry::factory()->forCategory($categoryA)->create(['name' => 'Alpha Industry', 'slug' => 'alpha-industry']);
    Industry::factory()->forCategory($categoryA)->create(['name' => 'Beta Sector', 'slug' => 'beta-sector']);
    Industry::factory()->forCategory($categoryA)->create(['name' => 'No Slug', 'slug' => '']);

    $cases = [
        'contains' => ['alpha', ['alpha-industry']],
        'does_not_contain' => ['!alpha', ['beta-sector', 'no-slug']],
        'starts_with' => ['starts(alpha)', ['alpha-industry']],
        'ends_with' => ['ends(sector)', ['beta-sector']],
        'empty' => ['empty()', []],
        'not_empty' => ['!empty()', ['alpha-industry', 'beta-sector', 'no-slug']],
    ];

    foreach ($cases as [$filterValue, $expectedSlugs]) {
        $response = metadataIndex($this, $this->user, 'metadata/industries', [
            'filter' => [
                'category_id' => $categoryA->id,
                'slug' => $filterValue,
            ],
            'page' => ['size' => 100],
        ]);

        $response->assertOk();
        $slugs = collect($response->json('data'))->pluck('slug')->all();
        expect($slugs)->toEqualCanonicalizing($expectedSlugs);
    }
});

it('supports set operators for category_id in industries', function (): void {
    $categoryA = Category::factory()->forModule(ModuleType::COMPANIES)->create(['name' => 'Category A']);
    $categoryB = Category::factory()->forModule(ModuleType::SERVICES)->create(['name' => 'Category B']);

    Industry::factory()->forCategory($categoryA)->create(['name' => 'Industry A']);
    Industry::factory()->forCategory($categoryB)->create(['name' => 'Industry B']);

    $cases = [
        'eq' => [$categoryA->id, [$categoryA->id]],
        'ne' => ['!'.$categoryA->id, [$categoryB->id]],
        'in' => [$categoryA->id.','.$categoryB->id, [$categoryA->id, $categoryB->id]],
        'not_in' => ['!'.$categoryA->id, [$categoryB->id]],
        'all_in' => ['all('.$categoryA->id.','.$categoryB->id.')', [$categoryA->id, $categoryB->id]],
        'not_all_in' => ['!all('.$categoryA->id.')', [$categoryB->id]],
    ];

    foreach ($cases as [$filterValue, $expectedValues]) {
        $response = metadataIndex($this, $this->user, 'metadata/industries', [
            'filter' => [
                'category_id' => $filterValue,
            ],
            'page' => ['size' => 100],
        ]);

        $response->assertOk();
        $values = collect($response->json('data'))->pluck('category_id')->unique()->values()->all();
        expect($values)->toEqualCanonicalizing($expectedValues);
    }
});

it('supports boolean operators for is_active in industries', function (): void {
    $categoryA = Category::factory()->forModule(ModuleType::COMPANIES)->create(['name' => 'Category A']);

    Industry::factory()->forCategory($categoryA)->create(['name' => 'Active', 'is_active' => true]);
    Industry::factory()->forCategory($categoryA)->create(['name' => 'Inactive', 'is_active' => false]);

    $cases = [
        'eq' => ['true', 1],
        'ne' => ['!true', 1],
        'empty' => ['empty()', 0],
        'not_empty' => ['!empty()', 2],
    ];

    foreach ($cases as [$filterValue, $expectedCount]) {
        $response = metadataIndex($this, $this->user, 'metadata/industries', [
            'filter' => [
                'category_id' => $categoryA->id,
                'is_active' => $filterValue,
            ],
            'page' => ['size' => 100],
        ]);

        $response->assertOk();
        $response->assertJsonCount($expectedCount, 'data');
    }
});

it('supports date operators for created_at in industries', function (): void {
    $categoryA = Category::factory()->forModule(ModuleType::COMPANIES)->create(['name' => 'Category A']);
    $dateA = CarbonImmutable::parse('2025-01-01 00:00:00');
    $dateB = CarbonImmutable::parse('2025-01-10 00:00:00');
    $dateC = CarbonImmutable::parse('2025-01-20 00:00:00');

    Industry::factory()->forCategory($categoryA)->create(['name' => 'Created A', 'created_at' => $dateA, 'updated_at' => $dateA]);
    Industry::factory()->forCategory($categoryA)->create(['name' => 'Created B', 'created_at' => $dateB, 'updated_at' => $dateB]);
    Industry::factory()->forCategory($categoryA)->create(['name' => 'Created C', 'created_at' => $dateC, 'updated_at' => $dateC]);

    $cases = [
        'eq' => ['2025-01-10 00:00:00', ['Created B']],
        'eq_date_only' => ['2025-01-10', ['Created B']],
        'ne' => ['!2025-01-10 00:00:00', ['Created A', 'Created C']],
        'gt' => ['>2025-01-10 00:00:00', ['Created C']],
        'gte' => ['>=2025-01-10 00:00:00', ['Created B', 'Created C']],
        'lt' => ['<2025-01-10 00:00:00', ['Created A']],
        'lte' => ['<=2025-01-10 00:00:00', ['Created A', 'Created B']],
        'between' => ['2025-01-01 00:00:00..2025-01-10 00:00:00', ['Created A', 'Created B']],
        'empty' => ['empty()', []],
        'not_empty' => ['!empty()', ['Created A', 'Created B', 'Created C']],
    ];

    foreach ($cases as [$filterValue, $expectedNames]) {
        $response = metadataIndex($this, $this->user, 'metadata/industries', [
            'filter' => [
                'category_id' => $categoryA->id,
                'created_at' => $filterValue,
            ],
            'page' => ['size' => 100],
        ]);

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        expect($names)->toEqualCanonicalizing($expectedNames);
    }
});

it('supports date operators for updated_at in industries', function (): void {
    $categoryA = Category::factory()->forModule(ModuleType::COMPANIES)->create(['name' => 'Category A']);
    $createdAt = CarbonImmutable::parse('2025-01-01 00:00:00');

    Industry::factory()->forCategory($categoryA)->create(['name' => 'Updated A', 'created_at' => $createdAt, 'updated_at' => CarbonImmutable::parse('2025-01-01 00:00:00')]);
    Industry::factory()->forCategory($categoryA)->create(['name' => 'Updated B', 'created_at' => $createdAt, 'updated_at' => CarbonImmutable::parse('2025-01-10 00:00:00')]);
    Industry::factory()->forCategory($categoryA)->create(['name' => 'Updated C', 'created_at' => $createdAt, 'updated_at' => CarbonImmutable::parse('2025-01-20 00:00:00')]);

    $cases = [
        'eq' => ['2025-01-10 00:00:00', ['Updated B']],
        'eq_date_only' => ['2025-01-10', ['Updated B']],
        'ne' => ['!2025-01-10 00:00:00', ['Updated A', 'Updated C']],
        'gt' => ['>2025-01-10 00:00:00', ['Updated C']],
        'gte' => ['>=2025-01-10 00:00:00', ['Updated B', 'Updated C']],
        'lt' => ['<2025-01-10 00:00:00', ['Updated A']],
        'lte' => ['<=2025-01-10 00:00:00', ['Updated A', 'Updated B']],
        'between' => ['2025-01-01 00:00:00..2025-01-10 00:00:00', ['Updated A', 'Updated B']],
        'empty' => ['empty()', []],
        'not_empty' => ['!empty()', ['Updated A', 'Updated B', 'Updated C']],
    ];

    foreach ($cases as [$filterValue, $expectedNames]) {
        $response = metadataIndex($this, $this->user, 'metadata/industries', [
            'filter' => [
                'category_id' => $categoryA->id,
                'updated_at' => $filterValue,
            ],
            'page' => ['size' => 100],
        ]);

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        expect($names)->toEqualCanonicalizing($expectedNames);
    }
});

it('rejects unsupported operators in industries', function (): void {
    Industry::factory()->create();

    $queries = [
        ['category_id' => '>invalid'],
        ['category_id' => 'invalid..other'],
        ['is_active' => 'true,false'],
    ];

    foreach ($queries as $filter) {
        $response = metadataIndex($this, $this->user, 'metadata/industries', [
            'filter' => $filter,
        ]);

        $response->assertUnprocessable();
    }
});

it('supports allowed sorting in industries', function (): void {
    $categoryA = Category::factory()->forModule(ModuleType::COMPANIES)->create(['name' => 'Category A']);

    Industry::factory()->forCategory($categoryA)->create([
        'name' => 'Zulu',
        'is_active' => true,
        'position' => 2,
        'created_at' => CarbonImmutable::parse('2025-01-20 00:00:00'),
        'updated_at' => CarbonImmutable::parse('2025-01-20 00:00:00'),
    ]);
    Industry::factory()->forCategory($categoryA)->create([
        'name' => 'Alpha',
        'is_active' => false,
        'position' => 1,
        'created_at' => CarbonImmutable::parse('2025-01-10 00:00:00'),
        'updated_at' => CarbonImmutable::parse('2025-01-10 00:00:00'),
    ]);

    $sorted = metadataIndex($this, $this->user, 'metadata/industries', [
        'filter' => ['category_id' => $categoryA->id],
        'sort' => '+name,+category_id,+is_active,+position,+created_at,+updated_at',
        'page' => ['size' => 100],
    ]);

    $sorted->assertOk();
    $names = collect($sorted->json('data'))->pluck('name')->all();
    expect($names)->toEqual(['Alpha', 'Zulu']);
});

it('rejects sorting by slug in industries', function (): void {
    Industry::factory()->create();

    $response = metadataIndex($this, $this->user, 'metadata/industries', [
        'sort' => '+slug',
    ]);

    $response->assertUnprocessable();
});
