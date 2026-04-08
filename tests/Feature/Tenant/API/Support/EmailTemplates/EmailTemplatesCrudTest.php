<?php

use App\Enums\EmailTemplateState;
use App\Enums\ModuleType;
use App\Models\Tenant\Metadata\Tag\Tag;
use App\Models\Tenant\Support\EmailTemplates\EmailTemplate;

// ── Helpers ─────────────────────────────────────────────────────────────────

function validTemplatePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Welcome Email',
        'description' => 'A welcome email for new contacts.',
        'module_type' => ModuleType::CONTACTS->value,
        'subject' => 'Welcome ${user_name}!',
        'email_preview_text' => 'You are welcome',
        'json_content' => ['type' => 'doc', 'content' => []],
        'html_content' => '<p>Hello ${user_name}!</p>',
        'reply_to_email' => 'support@example.com',
        'sender_name_override' => 'Support Team',
        'is_active' => true,
        'state' => EmailTemplateState::PUBLISHED->value,
    ], $overrides);
}

// ── Index ───────────────────────────────────────────────────────────────────

it('lists email templates and returns paginated data', function () {
    EmailTemplate::factory()->count(3)->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('email-templates?page[size]=25'));

    $response->assertOk();
    $response->assertJsonStructure(['data']);
    expect($response->json('data'))->toHaveCount(3);
});

it('filters email templates by module_type', function () {
    EmailTemplate::factory()->create(['module_type' => ModuleType::LEADS]);
    EmailTemplate::factory()->create(['module_type' => ModuleType::CONTACTS]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('email-templates?filter[module_type]=leads'));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.module_type'))->toBe('leads');
});

// ── Store ───────────────────────────────────────────────────────────────────

it('creates an email template with all fields and returns 201', function () {
    $tag = Tag::factory()->create(['module_type' => ModuleType::EMAIL_TEMPLATES]);

    $payload = validTemplatePayload(['tag_ids' => [$tag->id]]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('email-templates'), $payload);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Welcome Email');
    $response->assertJsonPath('data.subject', 'Welcome ${user_name}!');
    $response->assertJsonPath('data.module_type', 'contacts');
    $response->assertJsonPath('data.is_active', true);
    $response->assertJsonPath('data.state', 'published');
    expect($response->json('data.slug'))->not->toBeNull();
    expect($response->json('data.tags'))->toHaveCount(1);

    $this->assertDatabaseHas('email_templates', ['name' => 'Welcome Email']);
});

it('validates required fields on create', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('email-templates'), []);

    assertHasValidationError($response, 'name');
    assertHasValidationError($response, 'module_type');
    assertHasValidationError($response, 'subject');
    assertHasValidationError($response, 'json_content');
    assertHasValidationError($response, 'html_content');
});

it('rejects unknown placeholder variables on create', function () {
    $payload = validTemplatePayload([
        'html_content' => '<p>Hello ${unknown_var}!</p>',
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('email-templates'), $payload);

    assertHasValidationError($response, 'html_content');
});

it('rejects legacy email_subject and email_body aliases', function () {
    $payload = validTemplatePayload([
        'subject' => null,
        'html_content' => null,
        'email_subject' => 'Legacy subject',
        'email_body' => '<p>Legacy body</p>',
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('email-templates'), $payload);

    assertHasValidationError($response, 'subject');
    assertHasValidationError($response, 'html_content');
});

it('validates module_type is an allowed value', function () {
    $payload = validTemplatePayload(['module_type' => 'invalid_module']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('email-templates'), $payload);

    assertHasValidationError($response, 'module_type');
});

// ── Show ────────────────────────────────────────────────────────────────────

it('shows an email template with relations', function () {
    $template = EmailTemplate::factory()->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("email-templates/{$template->id}"));

    $response->assertOk();
    $response->assertJsonPath('data.id', $template->id);
    $response->assertJsonPath('data.name', $template->name);
    $response->assertJsonStructure([
        'data' => ['id', 'name', 'slug', 'module_type', 'subject', 'html_content', 'json_content', 'is_active', 'state'],
    ]);
});

it('returns 404 for non-existent template', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('email-templates/01NONEXISTENT'));

    $response->assertNotFound();
});

// ── Update ──────────────────────────────────────────────────────────────────

it('updates an email template partially', function () {
    $template = EmailTemplate::factory()->create([
        'name' => 'Original',
        'subject' => 'Hello ${user_name}',
        'html_content' => '<p>Hello ${user_name}</p>',
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("email-templates/{$template->id}"), [
            'name' => 'Updated',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.name', 'Updated');
    $response->assertJsonPath('data.subject', 'Hello ${user_name}');
});

it('updates only provided fields without touching others', function () {
    $template = EmailTemplate::factory()->create([
        'name' => 'Original',
        'subject' => 'Hello ${user_name}',
        'html_content' => '<p>Hi ${user_name}</p>',
        'reply_to_email' => 'old@example.com',
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("email-templates/{$template->id}"), [
            'reply_to_email' => 'new@example.com',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.name', 'Original');
    $response->assertJsonPath('data.subject', 'Hello ${user_name}');
    $response->assertJsonPath('data.reply_to_email', 'new@example.com');
});

it('rejects unknown placeholder variables on update', function () {
    $template = EmailTemplate::factory()->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("email-templates/{$template->id}"), [
            'html_content' => '<p>Hello ${bad_var}!</p>',
        ]);

    assertHasValidationError($response, 'html_content');
});

// ── Delete ──────────────────────────────────────────────────────────────────

it('soft-deletes an email template and returns 204', function () {
    $template = EmailTemplate::factory()->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("email-templates/{$template->id}"));

    $response->assertNoContent();

    expect(EmailTemplate::find($template->id))->toBeNull();
    expect(EmailTemplate::withTrashed()->find($template->id))->not->toBeNull();
});

// ── Restore ─────────────────────────────────────────────────────────────────

it('restores a soft-deleted email template', function () {
    $template = EmailTemplate::factory()->create();
    $template->delete();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("email-templates/{$template->id}/restore"));

    $response->assertOk();
    $response->assertJsonPath('data.id', $template->id);

    expect(EmailTemplate::find($template->id))->not->toBeNull();
});

// ── Force Delete ────────────────────────────────────────────────────────────

it('permanently deletes a soft-deleted email template', function () {
    $template = EmailTemplate::factory()->create();
    $template->delete();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("email-templates/{$template->id}/force"));

    $response->assertNoContent();

    expect(EmailTemplate::withTrashed()->find($template->id))->toBeNull();
});

it('returns 404 when force-deleting a non-trashed template', function () {
    $template = EmailTemplate::factory()->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("email-templates/{$template->id}/force"));

    $response->assertNotFound();
});

// ── Clone ───────────────────────────────────────────────────────────────────

it('clones an email template with draft state', function () {
    $tag = Tag::factory()->create(['module_type' => ModuleType::EMAIL_TEMPLATES]);
    $template = EmailTemplate::factory()->create(['name' => 'Original']);
    $template->syncTags([$tag->id]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("email-templates/{$template->id}/clone"));

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Copy of Original');
    $response->assertJsonPath('data.state', 'draft');
    $response->assertJsonPath('data.is_active', false);
    expect($response->json('data.id'))->not->toBe($template->id);
    expect($response->json('data.slug'))->not->toBe($template->slug);
    expect($response->json('data.tags'))->toHaveCount(1);
});

// ── Render ──────────────────────────────────────────────────────────────────

it('renders a published active template with system variables', function () {
    $template = EmailTemplate::factory()->create([
        'subject' => 'Hello ${user_name}',
        'html_content' => '<p>Hello ${user_name}!</p>',
        'is_active' => true,
        'state' => EmailTemplateState::PUBLISHED,
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("email-templates/{$template->id}/render"));

    $response->assertOk();
    $response->assertJsonPath('data.subject', "Hello {$this->user->name}");
    expect($response->json('data.html_content'))->toContain($this->user->name);
});

it('rejects render for inactive template', function () {
    $template = EmailTemplate::factory()->create([
        'is_active' => false,
        'state' => EmailTemplateState::PUBLISHED,
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("email-templates/{$template->id}/render"));

    $response->assertStatus(409);
});

it('rejects render for draft template', function () {
    $template = EmailTemplate::factory()->create([
        'is_active' => true,
        'state' => EmailTemplateState::DRAFT,
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("email-templates/{$template->id}/render"));

    $response->assertStatus(409);
});

// ── Preview ─────────────────────────────────────────────────────────────────

it('previews a draft template without requiring active or published state', function () {
    $template = EmailTemplate::factory()->draft()->create([
        'subject' => 'Preview ${user_name}',
        'html_content' => '<p>Preview ${user_name}!</p>',
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("email-templates/{$template->id}/preview"));

    $response->assertOk();
    $response->assertJsonPath('data.subject', "Preview {$this->user->name}");
});

// ── Slug ────────────────────────────────────────────────────────────────────

it('generates a unique slug on create', function () {
    $first = EmailTemplate::factory()->create(['name' => 'My Template']);
    $second = EmailTemplate::factory()->create(['name' => 'My Template']);

    expect($first->slug)->toBe('my-template');
    expect($second->slug)->not->toBe($first->slug);
    expect($second->slug)->toStartWith('my-template');
});

it('does not change slug on update', function () {
    $template = EmailTemplate::factory()->create(['name' => 'Original']);
    $originalSlug = $template->slug;

    $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("email-templates/{$template->id}"), [
            'name' => 'Updated Name',
        ]);

    $template->refresh();
    expect($template->slug)->toBe($originalSlug);
});
