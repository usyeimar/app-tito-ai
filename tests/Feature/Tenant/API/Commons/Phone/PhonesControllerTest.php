<?php

use App\Enums\ModuleType;
use App\Enums\PhoneLabel;
use App\Models\Tenant\Commons\Phones\Phone;
use App\Models\Tenant\CRM\Companies\Company;
use App\Models\Tenant\CRM\Contacts\Contact;
use App\Models\Tenant\CRM\Leads\Lead;

// ── Helpers ─────────────────────────────────────────────────────────────────

function createPhoneFor(Contact $contact, array $attrs = []): Phone
{
    $factory = Phone::factory()->forPhoneable($contact);

    if (($attrs['is_primary'] ?? false) === true) {
        $factory = $factory->primary();
        unset($attrs['is_primary']);
    }

    if (($attrs['label'] ?? null) instanceof PhoneLabel) {
        $factory = $factory->withLabel($attrs['label']);
        unset($attrs['label']);
    }

    if (isset($attrs['extension'])) {
        $factory = $factory->withExtension((string) $attrs['extension']);
        unset($attrs['extension']);
    }

    return $factory->create($attrs);
}

// ── CRUD ────────────────────────────────────────────────────────────────────

it('lists phones for a phoneable', function () {
    $contact = createContact();
    createPhoneFor($contact, ['phone' => '+12025551001', 'is_primary' => true]);
    createPhoneFor($contact, ['phone' => '+12025551002']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('phones?filter[phoneable_type]=contacts&filter[phoneable_id]='.$contact->id));

    $response->assertOk();
    $response->assertJsonStructure([
        'data',
        'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        'links',
    ]);
    $response->assertJsonCount(2, 'data');
    // Primary first
    $response->assertJsonPath('data.0.is_primary', true);
});

it('requires filter.phoneable_type and filter.phoneable_id for index', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('phones'));

    assertHasValidationError($response, 'filter.phoneable_type');
});

it('creates a phone and returns 201', function () {
    $contact = createContact();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('phones'), [
            'phoneable_type' => ModuleType::CONTACTS->value,
            'phoneable_id' => $contact->id,
            'phone' => '+12025551234',
            'country_code' => 'US',
            'label' => 'mobile',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.phone', '+12025551234');
    $response->assertJsonPath('data.label', 'mobile');
    $response->assertJsonPath('data.phoneable_type', 'contacts');
});

it('auto-promotes first phone to primary', function () {
    $contact = createContact();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('phones'), [
            'phoneable_type' => ModuleType::CONTACTS->value,
            'phoneable_id' => $contact->id,
            'phone' => '+12025551111',
            'country_code' => 'US',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.is_primary', true);
});

it('demotes existing primary when creating with is_primary: true', function () {
    $contact = createContact();
    $existing = createPhoneFor($contact, ['is_primary' => true, 'phone' => '+12025551001']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('phones'), [
            'phoneable_type' => ModuleType::CONTACTS->value,
            'phoneable_id' => $contact->id,
            'phone' => '+12025552002',
            'country_code' => 'US',
            'is_primary' => true,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.is_primary', true);
    expect($existing->fresh()->is_primary)->toBeFalse();
});

it('rejects duplicate phone with 409', function () {
    $contact = createContact();
    createPhoneFor($contact, ['phone' => '+12025551234']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('phones'), [
            'phoneable_type' => ModuleType::CONTACTS->value,
            'phoneable_id' => $contact->id,
            'phone' => '+12025551234',
            'country_code' => 'US',
        ]);

    $response->assertStatus(409);
});

it('updates phone number', function () {
    $contact = createContact();
    $phone = createPhoneFor($contact, ['phone' => '+12025551001']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("phones/{$phone->id}"), [
            'phone' => '+12025559999',
            'country_code' => 'US',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.phone', '+12025559999');
});

it('updates phone label', function () {
    $contact = createContact();
    $phone = createPhoneFor($contact, ['phone' => '+12025551001', 'label' => PhoneLabel::MOBILE]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("phones/{$phone->id}"), [
            'label' => 'office',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.label', 'office');
});

it('rejects is_primary: false on sole primary phone', function () {
    $contact = createContact();
    $phone = createPhoneFor($contact, ['is_primary' => true, 'phone' => '+12025551001']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("phones/{$phone->id}"), [
            'is_primary' => false,
        ]);

    $response->assertStatus(409);
});

it('makes a phone primary', function () {
    $contact = createContact();
    $primary = createPhoneFor($contact, ['is_primary' => true, 'phone' => '+12025551001']);
    $secondary = createPhoneFor($contact, ['phone' => '+12025552002']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("phones/{$secondary->id}/make-primary"));

    $response->assertOk();
    $response->assertJsonPath('data.is_primary', true);
    expect($primary->fresh()->is_primary)->toBeFalse();
});

it('shows a single phone', function () {
    $contact = createContact();
    $phone = createPhoneFor($contact, ['phone' => '+12025551001', 'extension' => '321']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("phones/{$phone->id}"));

    $response->assertOk();
    $response->assertJsonPath('data.id', $phone->id);
    $response->assertJsonPath('data.phone', '+12025551001');
});

it('deletes a phone and returns 204', function () {
    $contact = createContact();
    $phone = createPhoneFor($contact, ['phone' => '+12025551001']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("phones/{$phone->id}"));

    $response->assertNoContent();
    expect(Phone::query()->whereKey($phone->id)->exists())->toBeFalse();
});

it('auto-promotes next phone when deleting primary', function () {
    $contact = createContact();
    $primary = createPhoneFor($contact, ['is_primary' => true, 'phone' => '+12025551001']);
    $second = createPhoneFor($contact, ['phone' => '+12025552002']);

    $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("phones/{$primary->id}"))
        ->assertNoContent();

    expect($second->fresh()->is_primary)->toBeTrue();
});

it('returns all labels from the labels endpoint', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('phones/labels'));

    $expectedValues = array_map(
        static fn (PhoneLabel $label): string => $label->value,
        PhoneLabel::cases(),
    );
    $actualValues = collect($response->json('data'))
        ->pluck('value')
        ->all();

    $response->assertOk();
    $response->assertJsonCount(count(PhoneLabel::cases()), 'data');
    expect($actualValues)->toEqualCanonicalizing($expectedValues);
});

it('ensures exactly one primary after make-primary', function () {
    $contact = createContact();
    createPhoneFor($contact, ['phone' => '+12025551001']);
    $b = createPhoneFor($contact, ['phone' => '+12025552002', 'is_primary' => true]);

    $a = Phone::query()
        ->where('phoneable_id', $contact->id)
        ->where('phone', '+12025551001')
        ->first();

    $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("phones/{$a->id}/make-primary"))
        ->assertOk();

    expect(Phone::query()->where('phoneable_id', $contact->id)->where('is_primary', true)->count())->toBe(1);
    expect($a->fresh()->is_primary)->toBeTrue();
    expect($b->fresh()->is_primary)->toBeFalse();
});

// ── Validation ──────────────────────────────────────────────────────────────

it('rejects invalid phoneable_type', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('phones'), [
            'phoneable_type' => 'invalid_type',
            'phoneable_id' => 'some-id',
            'phone' => '+12025551234',
            'country_code' => 'US',
        ]);

    $response->assertUnprocessable();
});

it('rejects invalid label', function () {
    $contact = createContact();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('phones'), [
            'phoneable_type' => ModuleType::CONTACTS->value,
            'phoneable_id' => $contact->id,
            'phone' => '+12025551234',
            'country_code' => 'US',
            'label' => 'not_a_label',
        ]);

    $response->assertUnprocessable();
});

it('rejects missing required fields on create', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('phones'), []);

    $response->assertUnprocessable();
});

// ── Cross-type polymorphic coverage ────────────────────────────────────────

dataset('phoneable_parents', [
    'lead' => fn () => ['parent' => tap(Lead::factory()->make())->save(), 'type' => ModuleType::LEADS],
    'company' => fn () => ['parent' => tap(Company::factory()->make())->save(), 'type' => ModuleType::COMPANIES],
]);

it('creates and lists phones for non-contact parent types', function (array $ctx) {
    $parent = $ctx['parent'];
    $type = $ctx['type'];

    $createResponse = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('phones'), [
            'phoneable_type' => $type->value,
            'phoneable_id' => $parent->id,
            'phone' => '+12025553333',
            'country_code' => 'US',
        ]);

    $createResponse->assertCreated();
    $createResponse->assertJsonPath('data.phone', '+12025553333');
    $createResponse->assertJsonPath('data.phoneable_type', $type->value);

    $listResponse = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('phones?filter[phoneable_type]='.$type->value.'&filter[phoneable_id]='.$parent->id));

    $listResponse->assertOk();
    $listResponse->assertJsonCount(1, 'data');
})->with('phoneable_parents');

it('deletes phones for non-contact parent types', function (array $ctx) {
    $parent = $ctx['parent'];
    $phone = Phone::factory()->forPhoneable($parent)->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("phones/{$phone->id}"));

    $response->assertNoContent();
    expect(Phone::query()->whereKey($phone->id)->exists())->toBeFalse();
})->with('phoneable_parents');

// ── Authorization ──────────────────────────────────────────────────────────

it('returns 403 when user cannot view parent on show', function () {
    $contact = createContact();
    $phone = createPhoneFor($contact, ['phone' => '5551234567']);

    $unprivileged = $this->createTenantUser();

    $response = $this->actingAs($unprivileged, 'tenant-api')
        ->getJson($this->tenantApiUrl("phones/{$phone->id}"));

    $response->assertForbidden();
});

it('returns 403 when user cannot update parent on store', function () {
    $contact = createContact();
    $unprivileged = $this->createTenantUser();

    $response = $this->actingAs($unprivileged, 'tenant-api')
        ->postJson($this->tenantApiUrl('phones'), [
            'phoneable_type' => ModuleType::CONTACTS->value,
            'phoneable_id' => $contact->id,
            'phone' => '+12025551234',
            'country_code' => 'US',
        ]);

    $response->assertForbidden();
});

it('returns 403 when user cannot delete parent on destroy', function () {
    $contact = createContact();
    $phone = createPhoneFor($contact, ['phone' => '5550000000']);

    $unprivileged = $this->createTenantUser();

    $response = $this->actingAs($unprivileged, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("phones/{$phone->id}"));

    $response->assertForbidden();
});
