<?php

use App\Enums\DocumentTemplateState;
use App\Enums\ModuleType;
use App\Models\Tenant\Metadata\Tag\Tag;
use App\Models\Tenant\Support\DocumentTemplates\DocumentTemplate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

// ── Helpers ─────────────────────────────────────────────────────────────────

function createTestDocxFile(string $name = 'template.docx', array $variables = []): UploadedFile
{
    $phpWord = new PhpWord;
    $section = $phpWord->addSection();

    foreach ($variables as $var) {
        $section->addText('${'.$var.'}');
    }

    if ($variables === []) {
        $section->addText('Hello World');
    }

    $tempPath = tempnam(sys_get_temp_dir(), 'docx_test_').'.docx';
    $writer = IOFactory::createWriter($phpWord, 'Word2007');
    $writer->save($tempPath);

    return new UploadedFile($tempPath, $name, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
}

// ── Index ───────────────────────────────────────────────────────────────────

it('lists document templates and returns paginated data', function () {
    DocumentTemplate::factory()->count(3)->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('document-templates?page[size]=25'));

    $response->assertOk();
    $response->assertJsonStructure(['data']);
    expect($response->json('data'))->toHaveCount(3);
});

it('filters document templates by module_type', function () {
    DocumentTemplate::factory()->create(['module_type' => ModuleType::LEADS]);
    DocumentTemplate::factory()->create(['module_type' => ModuleType::CONTACTS]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('document-templates?filter[module_type]=leads'));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.module_type'))->toBe('leads');
});

// ── Store ───────────────────────────────────────────────────────────────────

it('creates a document template with file upload and returns 201', function () {
    Storage::fake();
    $tag = Tag::factory()->create(['module_type' => ModuleType::DOCUMENT_TEMPLATES]);
    $file = createTestDocxFile('invoice.docx', ['user_name', 'date']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('document-templates'), [
            'name' => 'Invoice Template',
            'description' => 'A template for invoices.',
            'module_type' => ModuleType::CONTACTS->value,
            'file' => $file,
            'is_active' => true,
            'state' => DocumentTemplateState::PUBLISHED->value,
            'tag_ids' => [$tag->id],
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Invoice Template');
    $response->assertJsonPath('data.module_type', 'contacts');
    $response->assertJsonPath('data.is_active', true);
    $response->assertJsonPath('data.state', 'published');
    expect($response->json('data.slug'))->not->toBeNull();
    expect($response->json('data.tags'))->toHaveCount(1);

    $this->assertDatabaseHas('document_templates', ['name' => 'Invoice Template']);
});

it('validates required fields on create', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('document-templates'), []);

    assertHasValidationError($response, 'name');
    assertHasValidationError($response, 'module_type');
    assertHasValidationError($response, 'file');
});

it('validates module_type is an allowed value', function () {
    Storage::fake();
    $file = createTestDocxFile();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('document-templates'), [
            'name' => 'Test',
            'module_type' => 'invalid_module',
            'file' => $file,
        ]);

    assertHasValidationError($response, 'module_type');
});

it('rejects unsupported file format', function () {
    $file = UploadedFile::fake()->create('template.pdf', 100, 'application/pdf');

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('document-templates'), [
            'name' => 'Test',
            'module_type' => ModuleType::CONTACTS->value,
            'file' => $file,
        ]);

    assertHasValidationError($response, 'file');
});

it('rejects unknown placeholder variables on create', function () {
    Storage::fake();
    $file = createTestDocxFile('template.docx', ['unknown_variable']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('document-templates'), [
            'name' => 'Test',
            'module_type' => ModuleType::CONTACTS->value,
            'file' => $file,
        ]);

    assertHasValidationError($response, 'file');
});

// ── Show ────────────────────────────────────────────────────────────────────

it('shows a document template with relations', function () {
    $template = DocumentTemplate::factory()->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("document-templates/{$template->id}"));

    $response->assertOk();
    $response->assertJsonPath('data.id', $template->id);
    $response->assertJsonPath('data.name', $template->name);
    $response->assertJsonStructure([
        'data' => ['id', 'name', 'slug', 'module_type', 'original_filename', 'mime_type', 'is_active', 'state'],
    ]);
});

it('returns 404 for non-existent template', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('document-templates/01NONEXISTENT'));

    $response->assertNotFound();
});

// ── Update ──────────────────────────────────────────────────────────────────

it('updates a document template partially', function () {
    $template = DocumentTemplate::factory()->create(['name' => 'Original']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("document-templates/{$template->id}"), [
            'name' => 'Updated',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.name', 'Updated');
});

it('does not change slug on update', function () {
    $template = DocumentTemplate::factory()->create(['name' => 'Original']);
    $originalSlug = $template->slug;

    $this->actingAs($this->user, 'tenant-api')
        ->patchJson($this->tenantApiUrl("document-templates/{$template->id}"), [
            'name' => 'Updated Name',
        ]);

    $template->refresh();
    expect($template->slug)->toBe($originalSlug);
});

// ── Delete ──────────────────────────────────────────────────────────────────

it('soft-deletes a document template and returns 204', function () {
    $template = DocumentTemplate::factory()->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("document-templates/{$template->id}"));

    $response->assertNoContent();

    expect(DocumentTemplate::find($template->id))->toBeNull();
    expect(DocumentTemplate::withTrashed()->find($template->id))->not->toBeNull();
});

// ── Restore ─────────────────────────────────────────────────────────────────

it('restores a soft-deleted document template', function () {
    $template = DocumentTemplate::factory()->create();
    $template->delete();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("document-templates/{$template->id}/restore"));

    $response->assertOk();
    $response->assertJsonPath('data.id', $template->id);

    expect(DocumentTemplate::find($template->id))->not->toBeNull();
});

// ── Force Delete ────────────────────────────────────────────────────────────

it('permanently deletes a soft-deleted document template', function () {
    $template = DocumentTemplate::factory()->create();
    $template->delete();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("document-templates/{$template->id}/force"));

    $response->assertNoContent();

    expect(DocumentTemplate::withTrashed()->find($template->id))->toBeNull();
});

it('returns 404 when force-deleting a non-trashed template', function () {
    $template = DocumentTemplate::factory()->create();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("document-templates/{$template->id}/force"));

    $response->assertNotFound();
});

// ── Clone ───────────────────────────────────────────────────────────────────

it('clones a document template with draft state', function () {
    Storage::fake();
    $tag = Tag::factory()->create(['module_type' => ModuleType::DOCUMENT_TEMPLATES]);
    $template = DocumentTemplate::factory()->create(['name' => 'Original']);
    $template->syncTags([$tag->id]);

    // Create a fake file at the template's storage path
    Storage::disk($template->disk)->put($template->storage_path, 'fake-docx-content');

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("document-templates/{$template->id}/clone"));

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Copy of Original');
    $response->assertJsonPath('data.state', 'draft');
    $response->assertJsonPath('data.is_active', false);
    expect($response->json('data.id'))->not->toBe($template->id);
    expect($response->json('data.slug'))->not->toBe($template->slug);
    expect($response->json('data.tags'))->toHaveCount(1);
});

// ── Slug ────────────────────────────────────────────────────────────────────

it('generates a unique slug on create', function () {
    $first = DocumentTemplate::factory()->create(['name' => 'My Template']);
    $second = DocumentTemplate::factory()->create(['name' => 'My Template']);

    expect($first->slug)->toBe('my-template');
    expect($second->slug)->not->toBe($first->slug);
    expect($second->slug)->toStartWith('my-template');
});
