<?php

use App\Enums\EmailLabel;
use App\Enums\ModuleType;
use App\Models\Tenant\Activity\ActivityEvent;
use App\Models\Tenant\Commons\Emails\Email;
use App\Models\Tenant\CRM\Companies\Company;
use App\Models\Tenant\CRM\Contacts\Contact;
use App\Models\Tenant\CRM\Leads\Lead;
use App\Models\Tenant\CRM\Properties\Property;

// ── Helpers ─────────────────────────────────────────────────────────────────

function createEmailFor(Contact $contact, array $attrs = []): Email
{
    $factory = Email::factory()->forEmailable($contact);

    if (($attrs['is_primary'] ?? false) === true) {
        $factory = $factory->primary();
        unset($attrs['is_primary']);
    }

    if (($attrs['label'] ?? null) instanceof EmailLabel) {
        $factory = $factory->withLabel($attrs['label']);
        unset($attrs['label']);
    }

    return $factory->create($attrs);
}

// ── CRUD ────────────────────────────────────────────────────────────────────

it('lists emails for an emailable', function () {
    $contact = createContact();
    createEmailFor($contact, ['email' => 'a@test.com', 'is_primary' => true]);
    createEmailFor($contact, ['email' => 'b@test.com']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('emails?filter[emailable_type]=contacts&filter[emailable_id]='.$contact->id));

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

it('requires filter.emailable_type and filter.emailable_id for index', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('emails'));

    assertHasValidationError($response, 'filter.emailable_type');
});

it('creates an email and returns 201', function () {
    $contact = createContact();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('emails'), [
            'emailable_type' => ModuleType::CONTACTS->value,
            'emailable_id' => $contact->id,
            'email' => 'john@example.com',
            'label' => 'work',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.email', 'john@example.com');
    $response->assertJsonPath('data.label', 'work');
    $response->assertJsonPath('data.emailable_type', 'contacts');
});

it('auto-promotes first email to primary', function () {
    $contact = createContact();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('emails'), [
            'emailable_type' => ModuleType::CONTACTS->value,
            'emailable_id' => $contact->id,
            'email' => 'first@example.com',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.is_primary', true);
});

it('demotes existing primary when creating with is_primary: true', function () {
    $contact = createContact();
    $existing = createEmailFor($contact, ['is_primary' => true, 'email' => 'old@test.com']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('emails'), [
            'emailable_type' => ModuleType::CONTACTS->value,
            'emailable_id' => $contact->id,
            'email' => 'new@test.com',
            'is_primary' => true,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.is_primary', true);
    expect($existing->fresh()->is_primary)->toBeFalse();
});

it('rejects duplicate email with 409', function () {
    $contact = createContact();
    createEmailFor($contact, ['email' => 'dupe@test.com']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('emails'), [
            'emailable_type' => ModuleType::CONTACTS->value,
            'emailable_id' => $contact->id,
            'email' => 'DUPE@test.com', // Case-insensitive duplicate
        ]);

    $response->assertStatus(409);
});

it('updates email address', function () {
    $contact = createContact();
    $email = createEmailFor($contact, ['email' => 'old@test.com']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("emails/{$email->id}"), [
            'email' => 'new@test.com',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.email', 'new@test.com');
});

it('updates email label', function () {
    $contact = createContact();
    $email = createEmailFor($contact, ['email' => 'test@test.com', 'label' => EmailLabel::WORK]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("emails/{$email->id}"), [
            'label' => 'personal',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.label', 'personal');
});

it('rejects is_primary: false on sole primary email', function () {
    $contact = createContact();
    $email = createEmailFor($contact, ['is_primary' => true, 'email' => 'only@test.com']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("emails/{$email->id}"), [
            'is_primary' => false,
        ]);

    $response->assertStatus(409);
});

it('makes an email primary', function () {
    $contact = createContact();
    $primary = createEmailFor($contact, ['is_primary' => true, 'email' => 'first@test.com']);
    $secondary = createEmailFor($contact, ['email' => 'second@test.com']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("emails/{$secondary->id}/make-primary"));

    $response->assertOk();
    $response->assertJsonPath('data.is_primary', true);
    expect($primary->fresh()->is_primary)->toBeFalse();
});

it('shows a single email', function () {
    $contact = createContact();
    $email = createEmailFor($contact, ['email' => 'show@test.com']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("emails/{$email->id}"));

    $response->assertOk();
    $response->assertJsonPath('data.id', $email->id);
    $response->assertJsonPath('data.email', 'show@test.com');
});

it('deletes an email and returns 204', function () {
    $contact = createContact();
    $email = createEmailFor($contact, ['email' => 'delete@test.com']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("emails/{$email->id}"));

    $response->assertNoContent();
    expect(Email::query()->whereKey($email->id)->exists())->toBeFalse();
});

it('auto-promotes next email when deleting primary', function () {
    $contact = createContact();
    $primary = createEmailFor($contact, ['is_primary' => true, 'email' => 'primary@test.com']);
    $second = createEmailFor($contact, ['email' => 'second@test.com']);

    $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("emails/{$primary->id}"))
        ->assertNoContent();

    expect($second->fresh()->is_primary)->toBeTrue();
});

it('returns all labels from the labels endpoint', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('emails/labels'));

    $expectedValues = array_map(
        static fn (EmailLabel $label): string => $label->value,
        EmailLabel::cases(),
    );
    $actualValues = collect($response->json('data'))
        ->pluck('value')
        ->all();

    $response->assertOk();
    $response->assertJsonCount(count(EmailLabel::cases()), 'data');
    expect($actualValues)->toEqualCanonicalizing($expectedValues);
});

it('ensures exactly one primary after make-primary', function () {
    $contact = createContact();
    createEmailFor($contact, ['email' => 'a@test.com']);
    $b = createEmailFor($contact, ['email' => 'b@test.com', 'is_primary' => true]);

    $a = Email::query()
        ->where('emailable_id', $contact->id)
        ->where('email', 'a@test.com')
        ->first();

    $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("emails/{$a->id}/make-primary"))
        ->assertOk();

    expect(Email::query()->where('emailable_id', $contact->id)->where('is_primary', true)->count())->toBe(1);
    expect($a->fresh()->is_primary)->toBeTrue();
    expect($b->fresh()->is_primary)->toBeFalse();
});

it('does not record activity when update has no changes', function () {
    $contact = createContact();
    $email = createEmailFor($contact, ['email' => 'same@test.com']);

    $countBefore = ActivityEvent::query()->count();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("emails/{$email->id}"), [
            'email' => 'same@test.com',
        ]);

    $response->assertOk();
    expect(ActivityEvent::query()->count())->toBe($countBefore);
});

it('rejects case-insensitive duplicate email on update with 409', function () {
    $contact = createContact();
    createEmailFor($contact, ['email' => 'existing@test.com']);
    $other = createEmailFor($contact, ['email' => 'other@test.com']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("emails/{$other->id}"), [
            'email' => 'EXISTING@test.com',
        ]);

    $response->assertStatus(409);
});

// ── Validation ──────────────────────────────────────────────────────────────

it('rejects invalid emailable_type', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('emails'), [
            'emailable_type' => 'invalid_type',
            'emailable_id' => 'some-id',
            'email' => 'test@test.com',
        ]);

    $response->assertUnprocessable();
});

it('rejects invalid label', function () {
    $contact = createContact();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('emails'), [
            'emailable_type' => ModuleType::CONTACTS->value,
            'emailable_id' => $contact->id,
            'email' => 'test@test.com',
            'label' => 'not_a_label',
        ]);

    $response->assertUnprocessable();
});

it('rejects missing required fields on create', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('emails'), []);

    $response->assertUnprocessable();
});

// ── Cross-type polymorphic coverage ────────────────────────────────────────

dataset('emailable_parents', [
    'lead' => fn () => ['parent' => tap(Lead::factory()->make())->save(), 'type' => ModuleType::LEADS],
    'company' => fn () => ['parent' => tap(Company::factory()->make())->save(), 'type' => ModuleType::COMPANIES],
    'property' => fn () => ['parent' => tap(Property::factory()->make())->save(), 'type' => ModuleType::PROPERTIES],
]);

it('creates and lists emails for non-contact parent types', function (array $ctx) {
    $parent = $ctx['parent'];
    $type = $ctx['type'];

    $createResponse = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('emails'), [
            'emailable_type' => $type->value,
            'emailable_id' => $parent->id,
            'email' => 'crosstype@example.com',
        ]);

    $createResponse->assertCreated();
    $createResponse->assertJsonPath('data.email', 'crosstype@example.com');
    $createResponse->assertJsonPath('data.emailable_type', $type->value);

    $listResponse = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('emails?filter[emailable_type]='.$type->value.'&filter[emailable_id]='.$parent->id));

    $listResponse->assertOk();
    $listResponse->assertJsonCount(1, 'data');
})->with('emailable_parents');

it('deletes emails for non-contact parent types', function (array $ctx) {
    $parent = $ctx['parent'];
    $email = Email::factory()->forEmailable($parent)->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("emails/{$email->id}"));

    $response->assertNoContent();
    expect(Email::query()->whereKey($email->id)->exists())->toBeFalse();
})->with('emailable_parents');

// ── Authorization ──────────────────────────────────────────────────────────

it('returns 403 when user cannot view parent on show', function () {
    $contact = createContact();
    $email = createEmailFor($contact, ['email' => 'auth@test.com']);

    $unprivileged = $this->createTenantUser();

    $response = $this->actingAs($unprivileged, 'tenant-api')
        ->getJson($this->tenantApiUrl("emails/{$email->id}"));

    $response->assertForbidden();
});

it('returns 403 when user cannot update parent on store', function () {
    $contact = createContact();
    $unprivileged = $this->createTenantUser();

    $response = $this->actingAs($unprivileged, 'tenant-api')
        ->postJson($this->tenantApiUrl('emails'), [
            'emailable_type' => ModuleType::CONTACTS->value,
            'emailable_id' => $contact->id,
            'email' => 'unauthorized@test.com',
        ]);

    $response->assertForbidden();
});

it('returns 403 when user cannot delete parent on destroy', function () {
    $contact = createContact();
    $email = createEmailFor($contact, ['email' => 'nodelete@test.com']);

    $unprivileged = $this->createTenantUser();

    $response = $this->actingAs($unprivileged, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("emails/{$email->id}"));

    $response->assertForbidden();
});

// ── Edge cases ─────────────────────────────────────────────────────────────

it('handles deleting last email gracefully without promotion', function () {
    $contact = createContact();
    $email = createEmailFor($contact, ['is_primary' => true, 'email' => 'only@test.com']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("emails/{$email->id}"));

    $response->assertNoContent();
    expect(Email::query()->where('emailable_id', $contact->id)->exists())->toBeFalse();
});
