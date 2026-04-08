<?php

use App\Jobs\Tenant\Commons\Addresses\GeocodeAddressJob;
use App\Models\Tenant\Commons\Addresses\Address;
use App\Services\Tenant\Commons\Addresses\AddressGeocoder;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

// ── GeocodeAddressJob ──────────────────────────────────────────────────────

it('skips geocoding when address no longer exists', function () {
    $job = new GeocodeAddressJob('non-existent-id');
    $geocoder = Mockery::mock(AddressGeocoder::class);
    $geocoder->shouldNotReceive('geocode');

    $job->handle($geocoder);
});

it('skips geocoding when address already has coordinates', function () {
    $contact = createContact();
    $address = Address::factory()->forAddressable($contact)->create([
        'lat' => 40.7128,
        'lng' => -74.0060,
    ]);

    $geocoder = Mockery::mock(AddressGeocoder::class);
    $geocoder->shouldNotReceive('geocode');

    (new GeocodeAddressJob($address->id))->handle($geocoder);
});

it('updates address when geocoder returns coordinates', function () {
    $contact = createContact();
    $address = Address::factory()->forAddressable($contact)->withoutCoordinates()->create();

    $geocoder = Mockery::mock(AddressGeocoder::class);
    $geocoder->shouldReceive('geocode')->once()->andReturn([
        'address_line' => $address->address_line,
        'lat' => 34.0522,
        'lng' => -118.2437,
    ]);

    (new GeocodeAddressJob($address->id))->handle($geocoder);

    $address->refresh();
    expect((float) $address->lat)->toBe(34.0522)
        ->and((float) $address->lng)->toBe(-118.2437);
});

it('does not update address when geocoder returns no coordinates', function () {
    $contact = createContact();
    $address = Address::factory()->forAddressable($contact)->withoutCoordinates()->create();

    $geocoder = Mockery::mock(AddressGeocoder::class);
    $geocoder->shouldReceive('geocode')->once()->andReturn([
        'address_line' => $address->address_line,
    ]);

    (new GeocodeAddressJob($address->id))->handle($geocoder);

    $address->refresh();
    expect($address->lat)->toBeNull()
        ->and($address->lng)->toBeNull();
});

it('updates only lat when geocoder returns partial coordinates', function () {
    $contact = createContact();
    $address = Address::factory()->forAddressable($contact)->withoutCoordinates()->create();

    $geocoder = Mockery::mock(AddressGeocoder::class);
    $geocoder->shouldReceive('geocode')->once()->andReturn([
        'address_line' => $address->address_line,
        'lat' => 34.0522,
    ]);

    (new GeocodeAddressJob($address->id))->handle($geocoder);

    $address->refresh();
    expect((float) $address->lat)->toBe(34.0522)
        ->and($address->lng)->toBeNull();
});

// ── AddressGeocoder ────────────────────────────────────────────────────────

it('returns fields unchanged when geocoder is disabled', function () {
    config(['services.google_maps.api_key' => '']);

    $geocoder = new AddressGeocoder;
    $fields = ['address_line' => '123 Main St', 'city' => 'Anytown'];

    $result = $geocoder->geocode($fields);

    expect($result)->toBe($fields);
});

it('returns fields unchanged when all address parts are empty', function () {
    config(['services.google_maps.api_key' => 'test-key']);

    $geocoder = new AddressGeocoder;
    $fields = ['address_line' => '', 'city' => null];

    $result = $geocoder->geocode($fields);

    expect($result)->toBe($fields);
});

it('returns fields unchanged when API returns non-OK status', function () {
    config(['services.google_maps.api_key' => 'test-key']);
    Http::fake([
        'maps.googleapis.com/*' => Http::response(['status' => 'ZERO_RESULTS'], 200),
        '*' => Http::response([], 200),
    ]);

    $geocoder = new AddressGeocoder;
    $fields = ['address_line' => '123 Main St'];

    $result = $geocoder->geocode($fields);

    expect($result)->toBe($fields);
});

it('returns fields unchanged when API request fails', function () {
    config(['services.google_maps.api_key' => 'test-key']);
    Http::fake([
        'maps.googleapis.com/*' => Http::response('Server Error', 500),
        '*' => Http::response([], 200),
    ]);

    $geocoder = new AddressGeocoder;
    $fields = ['address_line' => '123 Main St'];

    $result = $geocoder->geocode($fields);

    expect($result)->toBe($fields);
});

it('populates lat and lng from successful API response', function () {
    config(['services.google_maps.api_key' => 'test-key']);

    // Rebind a fresh Http Factory so TenantTestCase's catch-all stub is gone
    app()->forgetInstance(Factory::class);
    Http::swap(app()->make(Factory::class));
    Http::preventStrayRequests();
    Http::fake([
        'maps.googleapis.com/*' => Http::response([
            'status' => 'OK',
            'results' => [[
                'geometry' => [
                    'location' => ['lat' => 40.7128, 'lng' => -74.0060],
                ],
            ]],
        ], 200),
        '*' => Http::response([], 200),
    ]);

    $geocoder = new AddressGeocoder;
    $fields = ['address_line' => '123 Main St', 'city' => 'New York'];

    $result = $geocoder->geocode($fields);

    expect($result)->toHaveKey('lat', 40.7128)
        ->toHaveKey('lng', -74.0060);
});

it('returns fields unchanged when API throws an exception', function () {
    config(['services.google_maps.api_key' => 'test-key']);
    Http::fake([
        'maps.googleapis.com/*' => fn () => throw new RuntimeException('Connection timeout'),
        '*' => Http::response([], 200),
    ]);

    $geocoder = new AddressGeocoder;
    $fields = ['address_line' => '123 Main St'];

    $result = $geocoder->geocode($fields);

    expect($result)->toBe($fields);
});
