<?php

dataset('metadata index endpoints', [
    'statuses' => ['metadata/statuses'],
    'priorities' => ['metadata/priorities'],
    'types' => ['metadata/types'],
    'sources' => ['metadata/sources'],
    'tags' => ['metadata/tags'],
    'categories' => ['metadata/categories'],
    'license types' => ['metadata/license-types'],
    'industries' => ['metadata/industries'],
    'resource types' => ['metadata/resource-types'],
]);

it('returns the paginated metadata contract for index endpoints', function (string $endpoint): void {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl($endpoint.'?page[size]=10'));

    $response->assertOk();
    $response->assertJsonStructure([
        'data',
        'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        'links',
    ]);
})->with('metadata index endpoints');
