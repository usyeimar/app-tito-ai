<?php

use App\Enums\AddressLabel;
use App\Enums\ModuleType;
use App\Jobs\Tenant\Commons\Addresses\GeocodeAddressJob;
use App\Models\Tenant\Commons\Addresses\Address;
use App\Models\Tenant\CRM\Companies\Company;
use App\Models\Tenant\CRM\Contacts\Contact;
use App\Models\Tenant\CRM\Leads\Lead;
use Illuminate\Support\Facades\Queue;

// ── Helpers ─────────────────────────────────────────────────────────────────

function createAddressFor(Contact $contact, array $attrs = []): Address
{
    $factory = Address::factory()->forAddressable($contact);

    if (($attrs['is_primary'] ?? false) === true) {
        $factory = $factory->primary();
        unset($attrs['is_primary']);
    }

    if (($attrs['label'] ?? null) instanceof AddressLabel) {
        $factory = $factory->withLabel($attrs['label']);
        unset($attrs['label']);
    }

    if (array_key_exists('lat', $attrs) && array_key_exists('lng', $attrs) && $attrs['lat'] === null && $attrs['lng'] === null) {
        $factory = $factory->withoutCoordinates();
        unset($attrs['lat'], $attrs['lng']);
    }

    return $factory->create($attrs);
}

// ── CRUD ────────────────────────────────────────────────────────────────────

it('lists addresses for an addressable', function () {
    $contact = createContact();
    createAddressFor($contact, ['is_primary' => true]);
    createAddressFor($contact);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('addresses?filter[addressable_type]=contacts&filter[addressable_id]='.$contact->id));

    $response->assertOk();
    $response->assertJsonStructure([
        'data',
        'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        'links',
    ]);
    $response->assertJsonCount(2, 'data');
    $response->assertJsonPath('data.0.is_primary', true);
});

it('requires filter.addressable_type and filter.addressable_id for index', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('addresses'));

    assertHasValidationError($response, 'filter.addressable_type');
});

it('creates an address and returns 201', function () {
    $contact = createContact();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('addresses'), [
            'addressable_type' => ModuleType::CONTACTS->value,
            'addressable_id' => $contact->id,
            'address_line' => '123 Main St',
            'city' => 'Springfield',
            'state_region' => 'IL',
            'country_code' => 'US',
            'lat' => 39.7817,
            'lng' => -89.6501,
            'label' => 'headquarters',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.address_line', '123 Main St');
    $response->assertJsonPath('data.label', 'headquarters');
    $response->assertJsonPath('data.addressable_type', 'contacts');
});

it('auto-promotes first address to primary', function () {
    $contact = createContact();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('addresses'), [
            'addressable_type' => ModuleType::CONTACTS->value,
            'addressable_id' => $contact->id,
            'address_line' => '456 Oak Ave',
            'lat' => 40.7128,
            'lng' => -74.0060,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.is_primary', true);
});

it('demotes existing primary when creating with is_primary: true', function () {
    $contact = createContact();
    $existing = createAddressFor($contact, ['is_primary' => true]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('addresses'), [
            'addressable_type' => ModuleType::CONTACTS->value,
            'addressable_id' => $contact->id,
            'address_line' => '789 Elm St',
            'lat' => 34.0522,
            'lng' => -118.2437,
            'is_primary' => true,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.is_primary', true);
    expect($existing->fresh()->is_primary)->toBeFalse();
});

it('rejects duplicate address with 409', function () {
    $contact = createContact();
    createAddressFor($contact, [
        'address_line' => '123 Main St',
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('addresses'), [
            'addressable_type' => ModuleType::CONTACTS->value,
            'addressable_id' => $contact->id,
            'address_line' => '123 Main St',
        ]);

    $response->assertStatus(409);
});

it('rejects duplicate address case-insensitively with 409', function () {
    $contact = createContact();
    createAddressFor($contact, [
        'address_line' => '123 Main St',
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('addresses'), [
            'addressable_type' => ModuleType::CONTACTS->value,
            'addressable_id' => $contact->id,
            'address_line' => '123 MAIN ST',
        ]);

    $response->assertStatus(409);
});

it('rejects duplicate address with null coordinates with 409', function () {
    $contact = createContact();
    createAddressFor($contact, [
        'address_line' => '123 Main St',
        'lat' => null,
        'lng' => null,
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('addresses'), [
            'addressable_type' => ModuleType::CONTACTS->value,
            'addressable_id' => $contact->id,
            'address_line' => '123 Main St',
        ]);

    $response->assertStatus(409);
});

it('allows same address_line with different address_line_2', function () {
    $contact = createContact();
    createAddressFor($contact, [
        'address_line' => '123 Main St',
        'address_line_2' => 'Suite A',
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('addresses'), [
            'addressable_type' => ModuleType::CONTACTS->value,
            'addressable_id' => $contact->id,
            'address_line' => '123 Main St',
            'address_line_2' => 'Suite B',
        ]);

    $response->assertStatus(201);
});

it('updates address fields', function () {
    $contact = createContact();
    $address = createAddressFor($contact, ['lat' => null, 'lng' => null]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("addresses/{$address->id}"), [
            'address_line' => '999 Updated Blvd',
            'city' => 'New City',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.address_line', '999 Updated Blvd');
    $response->assertJsonPath('data.city', 'New City');
});

it('updates address label', function () {
    $contact = createContact();
    $address = createAddressFor($contact, ['label' => AddressLabel::HEADQUARTERS]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("addresses/{$address->id}"), [
            'label' => 'billing',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.label', 'billing');
});

it('rejects is_primary: false on sole primary address', function () {
    $contact = createContact();
    $address = createAddressFor($contact, ['is_primary' => true]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("addresses/{$address->id}"), [
            'is_primary' => false,
        ]);

    $response->assertStatus(409);
});

it('makes an address primary', function () {
    $contact = createContact();
    $primary = createAddressFor($contact, ['is_primary' => true]);
    $secondary = createAddressFor($contact);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("addresses/{$secondary->id}/make-primary"));

    $response->assertOk();
    $response->assertJsonPath('data.is_primary', true);
    expect($primary->fresh()->is_primary)->toBeFalse();
});

it('shows a single address', function () {
    $contact = createContact();
    $address = createAddressFor($contact);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("addresses/{$address->id}"));

    $response->assertOk();
    $response->assertJsonPath('data.id', $address->id);
    $response->assertJsonPath('data.address_line', $address->address_line);
});

it('deletes an address and returns 204', function () {
    $contact = createContact();
    $address = createAddressFor($contact);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("addresses/{$address->id}"));

    $response->assertNoContent();
    expect(Address::query()->whereKey($address->id)->exists())->toBeFalse();
});

it('auto-promotes next address when deleting primary', function () {
    $contact = createContact();
    $primary = createAddressFor($contact, ['is_primary' => true]);
    $second = createAddressFor($contact);

    $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("addresses/{$primary->id}"))
        ->assertNoContent();

    expect($second->fresh()->is_primary)->toBeTrue();
});

it('returns all labels from the labels endpoint', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('addresses/labels'));

    $expectedValues = array_map(
        static fn (AddressLabel $label): string => $label->value,
        AddressLabel::cases(),
    );
    $actualValues = collect($response->json('data'))
        ->pluck('value')
        ->all();

    $response->assertOk();
    $response->assertJsonCount(count(AddressLabel::cases()), 'data');
    expect($actualValues)->toEqualCanonicalizing($expectedValues);
});

it('ensures exactly one primary after make-primary', function () {
    $contact = createContact();
    createAddressFor($contact, ['address_line' => '100 First St']);
    $b = createAddressFor($contact, ['address_line' => '200 Second St', 'is_primary' => true]);

    $a = Address::query()
        ->where('addressable_id', $contact->id)
        ->where('address_line', '100 First St')
        ->first();

    $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("addresses/{$a->id}/make-primary"))
        ->assertOk();

    expect(Address::query()->where('addressable_id', $contact->id)->where('is_primary', true)->count())->toBe(1);
    expect($a->fresh()->is_primary)->toBeTrue();
    expect($b->fresh()->is_primary)->toBeFalse();
});

// ── Geocoding ───────────────────────────────────────────────────────────────

it('dispatches geocode job when lat/lng not provided', function () {
    Queue::fake();

    $contact = createContact();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('addresses'), [
            'addressable_type' => ModuleType::CONTACTS->value,
            'addressable_id' => $contact->id,
            'address_line' => '123 Broadway',
            'city' => 'New York',
            'state_region' => 'NY',
            'country_code' => 'US',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.lat', null);
    $response->assertJsonPath('data.lng', null);
    Queue::assertPushed(GeocodeAddressJob::class);
});

it('skips geocode job when lat/lng are explicitly provided', function () {
    Queue::fake();

    $contact = createContact();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('addresses'), [
            'addressable_type' => ModuleType::CONTACTS->value,
            'addressable_id' => $contact->id,
            'address_line' => '456 Test Ave',
            'lat' => 51.5074,
            'lng' => -0.1278,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.lat', 51.5074);
    $response->assertJsonPath('data.lng', -0.1278);
    Queue::assertNotPushed(GeocodeAddressJob::class);
});

it('creates address without coords and dispatches geocode job', function () {
    Queue::fake();

    $contact = createContact();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('addresses'), [
            'addressable_type' => ModuleType::CONTACTS->value,
            'addressable_id' => $contact->id,
            'address_line' => '789 Fail Rd',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.lat', null);
    $response->assertJsonPath('data.lng', null);
    Queue::assertPushed(GeocodeAddressJob::class);
});

// ── Validation ──────────────────────────────────────────────────────────────

it('rejects invalid addressable_type', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('addresses'), [
            'addressable_type' => 'invalid_type',
            'addressable_id' => 'some-id',
            'address_line' => '123 Main St',
            'lat' => 40.0,
            'lng' => -74.0,
        ]);

    $response->assertUnprocessable();
});

it('rejects invalid label', function () {
    $contact = createContact();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('addresses'), [
            'addressable_type' => ModuleType::CONTACTS->value,
            'addressable_id' => $contact->id,
            'address_line' => '123 Main St',
            'lat' => 40.0,
            'lng' => -74.0,
            'label' => 'not_a_label',
        ]);

    $response->assertUnprocessable();
});

it('rejects missing required fields on create', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('addresses'), []);

    $response->assertUnprocessable();
});

// ── Cross-type polymorphic coverage ────────────────────────────────────────

dataset('addressable_parents', [
    'lead' => fn () => ['parent' => tap(Lead::factory()->make())->save(), 'type' => ModuleType::LEADS],
    'company' => fn () => ['parent' => tap(Company::factory()->make())->save(), 'type' => ModuleType::COMPANIES],
]);

it('creates and lists addresses for non-contact parent types', function (array $ctx) {
    $parent = $ctx['parent'];
    $type = $ctx['type'];

    $createResponse = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('addresses'), [
            'addressable_type' => $type->value,
            'addressable_id' => $parent->id,
            'address_line' => '100 Cross-Type St',
            'lat' => 40.0,
            'lng' => -74.0,
        ]);

    $createResponse->assertCreated();
    $createResponse->assertJsonPath('data.address_line', '100 Cross-Type St');
    $createResponse->assertJsonPath('data.addressable_type', $type->value);

    $listResponse = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('addresses?filter[addressable_type]='.$type->value.'&filter[addressable_id]='.$parent->id));

    $listResponse->assertOk();
    $listResponse->assertJsonCount(1, 'data');
})->with('addressable_parents');

it('deletes addresses for non-contact parent types', function (array $ctx) {
    $parent = $ctx['parent'];
    $address = Address::factory()->forAddressable($parent)->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("addresses/{$address->id}"));

    $response->assertNoContent();
    expect(Address::query()->whereKey($address->id)->exists())->toBeFalse();
})->with('addressable_parents');

// ── Authorization ──────────────────────────────────────────────────────────

it('returns 403 when user cannot view parent on show', function () {
    $contact = createContact();
    $address = createAddressFor($contact);

    $unprivileged = $this->createTenantUser();

    $response = $this->actingAs($unprivileged, 'tenant-api')
        ->getJson($this->tenantApiUrl("addresses/{$address->id}"));

    $response->assertForbidden();
});

it('returns 403 when user cannot update parent on store', function () {
    $contact = createContact();
    $unprivileged = $this->createTenantUser();

    $response = $this->actingAs($unprivileged, 'tenant-api')
        ->postJson($this->tenantApiUrl('addresses'), [
            'addressable_type' => ModuleType::CONTACTS->value,
            'addressable_id' => $contact->id,
            'address_line' => '123 Unauthorized St',
        ]);

    $response->assertForbidden();
});

it('returns 403 when user cannot delete parent on destroy', function () {
    $contact = createContact();
    $address = createAddressFor($contact);

    $unprivileged = $this->createTenantUser();

    $response = $this->actingAs($unprivileged, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("addresses/{$address->id}"));

    $response->assertForbidden();
});

// ── Update geocoding ───────────────────────────────────────────────────────

it('dispatches geocode job on update when address fields change and no coords exist', function () {
    Queue::fake();

    $contact = createContact();
    $address = createAddressFor($contact, ['lat' => null, 'lng' => null]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("addresses/{$address->id}"), [
            'address_line' => '999 New Address Blvd',
        ]);

    $response->assertOk();
    Queue::assertPushed(GeocodeAddressJob::class);
});

it('skips geocode job on update when only label changes', function () {
    Queue::fake();

    $contact = createContact();
    $address = createAddressFor($contact, ['lat' => null, 'lng' => null]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("addresses/{$address->id}"), [
            'label' => 'billing',
        ]);

    $response->assertOk();
    Queue::assertNotPushed(GeocodeAddressJob::class);
});

it('skips geocode job on update when lat/lng explicitly provided', function () {
    Queue::fake();

    $contact = createContact();
    $address = createAddressFor($contact, ['lat' => null, 'lng' => null]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("addresses/{$address->id}"), [
            'address_line' => '888 With Coords Ave',
            'lat' => 51.5074,
            'lng' => -0.1278,
        ]);

    $response->assertOk();
    Queue::assertNotPushed(GeocodeAddressJob::class);
});
