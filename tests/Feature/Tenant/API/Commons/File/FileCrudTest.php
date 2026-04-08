<?php

use App\Actions\Tenant\Commons\File\ForceDeleteFile;
use App\Models\Tenant\Commons\Files\File;
use App\Models\Tenant\Commons\Files\FileFolder;
use App\Models\Tenant\CRM\Leads\Lead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// ── Helpers ─────────────────────────────────────────────────────────────────

function createLead(): Lead
{
    $lead = Lead::factory()->make();
    $lead->save();

    return $lead;
}

function createFileFor(Lead $lead, array $attrs = []): File
{
    return File::factory()->forFileable($lead)->create($attrs);
}

function createFolderFor(Lead $lead, array $attrs = []): FileFolder
{
    return FileFolder::factory()->forFileable($lead)->create($attrs);
}

// ── Rename ──────────────────────────────────────────────────────────────────

it('renames a file', function () {
    $lead = createLead();
    $file = createFileFor($lead, ['name' => 'old-name.jpg']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->putJson($this->tenantApiUrl("files/{$file->id}"), [
            'name' => 'new-name.jpg',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.name', 'new-name.jpg');
    expect($file->fresh()->name)->toBe('new-name.jpg');
});

it('rejects rename with duplicate sibling name', function () {
    $lead = createLead();
    createFileFor($lead, ['name' => 'taken.jpg']);
    $file = createFileFor($lead, ['name' => 'original.jpg']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->putJson($this->tenantApiUrl("files/{$file->id}"), [
            'name' => 'taken.jpg',
        ]);

    $response->assertStatus(409);
});

// ── Move ────────────────────────────────────────────────────────────────────

it('moves a file to a folder', function () {
    $lead = createLead();
    $file = createFileFor($lead);
    $folder = createFolderFor($lead, ['name' => 'destination']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("files/{$file->id}/move"), [
            'destination_folder_id' => $folder->id,
        ]);

    $response->assertSuccessful();
    expect($file->fresh()->folder_id)->toBe($folder->id);
});

it('moves a file to root', function () {
    $lead = createLead();
    $folder = createFolderFor($lead);
    $file = createFileFor($lead, ['folder_id' => $folder->id]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("files/{$file->id}/move"), [
            'destination_folder_id' => null,
        ]);

    $response->assertSuccessful();
    expect($file->fresh()->folder_id)->toBeNull();
});

// ── Soft Delete ─────────────────────────────────────────────────────────────

it('soft-deletes a file', function () {
    $lead = createLead();
    $file = createFileFor($lead);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("files/{$file->id}"));

    $response->assertOk();
    expect(File::find($file->id))->toBeNull();
    expect(File::withTrashed()->find($file->id))->not->toBeNull();
});

// ── Restore ─────────────────────────────────────────────────────────────────

it('restores a soft-deleted file', function () {
    $lead = createLead();
    $file = createFileFor($lead);
    $file->delete();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("files/{$file->id}/restore"));

    $response->assertSuccessful();
    $response->assertJsonPath('data.id', $file->id);
    expect(File::find($file->id))->not->toBeNull();
});

// ── Force Delete ────────────────────────────────────────────────────────────

it('force-deletes a file and removes it from storage', function () {
    Storage::fake('s3');

    $lead = createLead();
    $file = createFileFor($lead, [
        'disk' => 's3',
        'storage_path' => 'files/test-file.jpg',
    ]);

    Storage::disk('s3')->put('files/test-file.jpg', 'content');
    $file->delete();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("files/{$file->id}/force"));

    $response->assertOk();
    expect(File::withTrashed()->find($file->id))->toBeNull();
    Storage::disk('s3')->assertMissing('files/test-file.jpg');
});

it('does not delete storage when outer transaction rolls back', function () {
    Storage::fake('s3');

    $lead = createLead();
    $file = createFileFor($lead, [
        'disk' => 's3',
        'storage_path' => 'files/rollback-test-file.jpg',
    ]);

    Storage::disk('s3')->put('files/rollback-test-file.jpg', 'content');
    $file->delete();

    DB::beginTransaction();

    try {
        app(ForceDeleteFile::class)($this->user, $file->fresh());

        Storage::disk('s3')->assertExists('files/rollback-test-file.jpg');
        DB::rollBack();
    } catch (Throwable $e) {
        DB::rollBack();

        throw $e;
    }

    expect(File::withTrashed()->find($file->id))->not->toBeNull();
    Storage::disk('s3')->assertExists('files/rollback-test-file.jpg');
});

// ── Download ────────────────────────────────────────────────────────────────

it('downloads a file', function () {
    Storage::fake('s3');

    $lead = createLead();
    $file = createFileFor($lead, [
        'disk' => 's3',
        'storage_path' => 'files/download-test.jpg',
        'name' => 'my-photo.jpg',
    ]);

    Storage::disk('s3')->put('files/download-test.jpg', 'file-content');

    $response = $this->actingAs($this->user, 'tenant-api')
        ->get($this->tenantApiUrl("files/{$file->id}/download"));

    $response->assertOk();
    $response->assertHeader('content-disposition');
});

it('returns 404 when downloading a file with missing storage', function () {
    Storage::fake('s3');

    $lead = createLead();
    $file = createFileFor($lead, [
        'disk' => 's3',
        'storage_path' => 'files/does-not-exist.jpg',
    ]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->get($this->tenantApiUrl("files/{$file->id}/download"));

    $response->assertNotFound();
});
