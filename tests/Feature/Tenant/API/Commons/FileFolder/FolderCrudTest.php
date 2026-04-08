<?php

use App\Models\Tenant\Commons\Files\File;
use App\Models\Tenant\Commons\Files\FileFolder;
use App\Models\Tenant\CRM\Leads\Lead;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

// ── Helpers ─────────────────────────────────────────────────────────────────

function folderCrudLead(): Lead
{
    $lead = Lead::factory()->make();
    $lead->save();

    return $lead;
}

function folderCrudFolder(Lead $lead, array $attrs = []): FileFolder
{
    $factory = FileFolder::factory()->forFileable($lead);

    if (isset($attrs['parent_id'])) {
        $parent = FileFolder::query()->findOrFail($attrs['parent_id']);
        $factory = $factory->inFolder($parent);
        unset($attrs['parent_id']);
    }

    return $factory->create($attrs);
}

function folderCrudFile(Lead $lead, array $attrs = []): File
{
    return File::factory()->forFileable($lead)->create($attrs);
}

// ── Create ──────────────────────────────────────────────────────────────────

it('creates a folder', function () {
    $lead = folderCrudLead();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/folders'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
            'name' => 'Documents',
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'Documents');
    $response->assertJsonPath('data.fileable_type', 'lead');
});

it('creates a nested folder', function () {
    $lead = folderCrudLead();
    $parent = folderCrudFolder($lead, ['name' => 'parent']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/folders'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
            'name' => 'child',
            'parent_id' => $parent->id,
        ]);

    $response->assertCreated();
    $response->assertJsonPath('data.parent_id', $parent->id);
});

it('rejects duplicate folder name at same level', function () {
    $lead = folderCrudLead();
    folderCrudFolder($lead, ['name' => 'taken']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/folders'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
            'name' => 'taken',
        ]);

    $response->assertStatus(409);
});

// ── Rename ──────────────────────────────────────────────────────────────────

it('renames a folder', function () {
    $lead = folderCrudLead();
    $folder = folderCrudFolder($lead, ['name' => 'old']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->putJson($this->tenantApiUrl("files/folders/{$folder->id}"), [
            'name' => 'new',
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.name', 'new');
    expect($folder->fresh()->name)->toBe('new');
});

// ── Move ────────────────────────────────────────────────────────────────────

it('moves a folder to another parent', function () {
    $lead = folderCrudLead();
    $folderA = folderCrudFolder($lead, ['name' => 'a']);
    $folderB = folderCrudFolder($lead, ['name' => 'b']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("files/folders/{$folderA->id}/move"), [
            'destination_parent_id' => $folderB->id,
        ]);

    $response->assertSuccessful();
    expect($folderA->fresh()->parent_id)->toBe($folderB->id);
});

it('prevents moving a folder into its own descendant', function () {
    $lead = folderCrudLead();
    $parent = folderCrudFolder($lead, ['name' => 'parent']);
    $child = folderCrudFolder($lead, ['name' => 'child', 'parent_id' => $parent->id]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("files/folders/{$parent->id}/move"), [
            'destination_parent_id' => $child->id,
        ]);

    $response->assertStatus(422);
});

// ── Delete + Cascade ────────────────────────────────────────────────────────

it('soft-deletes a folder and cascades to contents', function () {
    $lead = folderCrudLead();
    $folder = folderCrudFolder($lead);
    $file = folderCrudFile($lead, ['folder_id' => $folder->id]);
    $childFolder = folderCrudFolder($lead, ['parent_id' => $folder->id, 'name' => 'sub']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("files/folders/{$folder->id}"));

    $response->assertOk();
    expect(FileFolder::find($folder->id))->toBeNull();
    expect(File::find($file->id))->toBeNull();
    expect(FileFolder::find($childFolder->id))->toBeNull();

    // But still exist as trashed
    expect(FileFolder::withTrashed()->find($folder->id))->not->toBeNull();
    expect(File::withTrashed()->find($file->id))->not->toBeNull();
});

// ── Restore + Cascade ───────────────────────────────────────────────────────

it('restores a folder and cascades to contents', function () {
    $lead = folderCrudLead();
    $folder = folderCrudFolder($lead);
    $file = folderCrudFile($lead, ['folder_id' => $folder->id]);
    $childFolder = folderCrudFolder($lead, ['parent_id' => $folder->id, 'name' => 'sub']);

    $folder->delete();
    $file->delete();
    $childFolder->delete();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl("files/folders/{$folder->id}/restore"));

    $response->assertSuccessful();
    expect(FileFolder::find($folder->id))->not->toBeNull();
    expect(File::find($file->id))->not->toBeNull();
    expect(FileFolder::find($childFolder->id))->not->toBeNull();
});

// ── Force Delete ────────────────────────────────────────────────────────────

it('force-deletes a folder and all contents', function () {
    Storage::fake('s3');

    $lead = folderCrudLead();
    $folder = folderCrudFolder($lead);
    $file = folderCrudFile($lead, [
        'folder_id' => $folder->id,
        'disk' => 's3',
        'storage_path' => 'files/nested.jpg',
    ]);

    Storage::disk('s3')->put('files/nested.jpg', 'data');
    $folder->delete();
    $file->delete();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("files/folders/{$folder->id}/force"));

    $response->assertOk();
    expect(FileFolder::withTrashed()->find($folder->id))->toBeNull();
    expect(File::withTrashed()->find($file->id))->toBeNull();
    Storage::disk('s3')->assertMissing('files/nested.jpg');
});

it('force-delete continues on partial storage failure and logs orphaned objects', function () {
    $lead = folderCrudLead();
    $folder = folderCrudFolder($lead);
    $failingFile = folderCrudFile($lead, [
        'folder_id' => $folder->id,
        'disk' => 's3-fail',
        'storage_path' => 'files/orphan-fail.jpg',
    ]);
    $successfulFile = folderCrudFile($lead, [
        'folder_id' => $folder->id,
        'disk' => 's3-ok',
        'storage_path' => 'files/orphan-ok.jpg',
    ]);

    $folder->delete();
    $failingFile->delete();
    $successfulFile->delete();

    $failingDisk = Mockery::mock(Filesystem::class);
    $failingDisk->shouldReceive('exists')
        ->once()
        ->with('files/orphan-fail.jpg')
        ->andReturn(true);
    $failingDisk->shouldReceive('delete')
        ->once()
        ->with('files/orphan-fail.jpg')
        ->andThrow(new RuntimeException('Simulated S3 delete failure.'));

    $successfulDisk = Mockery::mock(Filesystem::class);
    $successfulDisk->shouldReceive('exists')
        ->once()
        ->with('files/orphan-ok.jpg')
        ->andReturn(true);
    $successfulDisk->shouldReceive('delete')
        ->once()
        ->with('files/orphan-ok.jpg')
        ->andReturn(true);

    Storage::shouldReceive('disk')->once()->with('s3-fail')->andReturn($failingDisk);
    Storage::shouldReceive('disk')->once()->with('s3-ok')->andReturn($successfulDisk);

    Log::spy();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->deleteJson($this->tenantApiUrl("files/folders/{$folder->id}/force"));

    $response->assertOk();
    expect(FileFolder::withTrashed()->find($folder->id))->toBeNull();
    expect(File::withTrashed()->find($failingFile->id))->toBeNull();
    expect(File::withTrashed()->find($successfulFile->id))->toBeNull();

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(function (string $message, array $context) use ($failingFile): bool {
            return $message === 'Failed to delete file from storage during folder force-delete.'
                && ($context['file_id'] ?? null) === (string) $failingFile->id
                && ($context['disk'] ?? null) === 's3-fail'
                && ($context['storage_path'] ?? null) === 'files/orphan-fail.jpg'
                && str_contains((string) ($context['error'] ?? ''), 'Simulated S3 delete failure.');
        });

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function (string $message, array $context) use ($failingFile): bool {
            $failedTargets = $context['failed_targets'] ?? null;

            return $message === 'Folder force-delete completed with orphaned storage objects.'
                && ($context['failed_count'] ?? null) === 1
                && is_array($failedTargets)
                && ($failedTargets[0]['file_id'] ?? null) === (string) $failingFile->id
                && ($failedTargets[0]['disk'] ?? null) === 's3-fail'
                && ($failedTargets[0]['storage_path'] ?? null) === 'files/orphan-fail.jpg';
        });
});
