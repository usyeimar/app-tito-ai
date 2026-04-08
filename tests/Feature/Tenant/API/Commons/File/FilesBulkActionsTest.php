<?php

use App\Actions\Tenant\Commons\File\ForceDeleteFile;
use App\Jobs\Tenant\Commons\Files\FinalizePendingFileDeletionJob;
use App\Models\Tenant\Activity\ActivityEvent;
use App\Models\Tenant\Commons\Files\File;
use App\Models\Tenant\Commons\Files\FileFolder;
use App\Models\Tenant\CRM\Leads\Lead;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function bulkLead(): Lead
{
    return Lead::factory()->create();
}

function bulkFileFor(Lead $lead, array $attrs = []): File
{
    return File::factory()->forFileable($lead)->create($attrs);
}

function bulkFolderFor(Lead $lead, array $attrs = []): FileFolder
{
    return FileFolder::factory()->forFileable($lead)->create($attrs);
}

it('bulk moves files to a folder', function () {
    $lead = bulkLead();
    $folder = bulkFolderFor($lead, ['name' => 'target']);
    $a = bulkFileFor($lead, ['name' => 'a.txt']);
    $b = bulkFileFor($lead, ['name' => 'b.txt']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/bulk/move'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
            'file_ids' => [$a->id, $b->id],
            'destination_folder_id' => $folder->id,
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.moved_count', 2);
    expect($a->fresh()->folder_id)->toBe($folder->id);
    expect($b->fresh()->folder_id)->toBe($folder->id);
});

it('denies bulk move for user without permissions', function () {
    $lead = bulkLead();
    $file = bulkFileFor($lead);
    $folder = bulkFolderFor($lead);
    $regularUser = $this->createTenantUser();

    $response = $this->actingAs($regularUser, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/bulk/move'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
            'file_ids' => [$file->id],
            'destination_folder_id' => $folder->id,
        ]);

    $response->assertForbidden();
});

it('validates bulk move payload', function () {
    $lead = bulkLead();
    $file = bulkFileFor($lead);

    $emptyIdsResponse = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/bulk/move'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
            'file_ids' => [],
            'destination_folder_id' => null,
        ]);

    $emptyIdsResponse->assertUnprocessable();
    assertHasValidationError($emptyIdsResponse, 'file_ids');

    $invalidFolderResponse = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/bulk/move'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
            'file_ids' => [$file->id],
            'destination_folder_id' => (string) Str::ulid(),
        ]);

    $invalidFolderResponse->assertUnprocessable();
    assertHasValidationError($invalidFolderResponse, 'destination_folder_id');
});

it('rejects bulk move when selected files do not belong to fileable', function () {
    $lead = bulkLead();
    $otherLead = bulkLead();
    $foreignFile = bulkFileFor($otherLead);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/bulk/move'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
            'file_ids' => [$foreignFile->id],
            'destination_folder_id' => null,
        ]);

    $response->assertStatus(422);
    $response->assertJsonPath('errors.0.code', 'COMMONS_FILE_HIERARCHY_INVALID');
});

it('bulk soft-deletes files and returns accurate count', function () {
    $lead = bulkLead();
    $a = bulkFileFor($lead);
    $b = bulkFileFor($lead);
    $c = bulkFileFor($lead);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/bulk/delete'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
            'file_ids' => [$a->id, $b->id, $c->id],
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.deleted_count', 3);
    expect(File::find($a->id))->toBeNull();
    expect(File::find($b->id))->toBeNull();
    expect(File::find($c->id))->toBeNull();
});

it('denies bulk delete for user without permissions', function () {
    $lead = bulkLead();
    $file = bulkFileFor($lead);
    $regularUser = $this->createTenantUser();

    $response = $this->actingAs($regularUser, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/bulk/delete'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
            'file_ids' => [$file->id],
        ]);

    $response->assertForbidden();
});

it('rejects bulk delete for non-existent file ids', function () {
    $lead = bulkLead();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/bulk/delete'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
            'file_ids' => [(string) Str::ulid()],
        ]);

    $response->assertUnprocessable();
    assertHasValidationError($response, 'file_ids.0');
});

it('bulk soft-deletes only active files in mixed state', function () {
    $lead = bulkLead();
    $a = bulkFileFor($lead);
    $b = bulkFileFor($lead);
    $c = bulkFileFor($lead);

    $b->delete();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/bulk/delete'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
            'file_ids' => [$a->id, $b->id, $c->id],
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.deleted_count', 2);

    expect(File::find($a->id))->toBeNull();
    expect(File::find($b->id))->toBeNull();
    expect(File::find($c->id))->toBeNull();
    expect(File::withTrashed()->find($b->id)?->trashed())->toBeTrue();
});

it('bulk restores files and returns accurate count', function () {
    $lead = bulkLead();
    $a = bulkFileFor($lead);
    $b = bulkFileFor($lead);

    $a->delete();
    $b->delete();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/bulk/restore'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
            'file_ids' => [$a->id, $b->id],
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.restored_count', 2);
    expect($a->fresh()->deleted_at)->toBeNull();
    expect($b->fresh()->deleted_at)->toBeNull();
});

it('denies bulk restore for user without permissions', function () {
    $lead = bulkLead();
    $file = bulkFileFor($lead);
    $file->delete();
    $regularUser = $this->createTenantUser();

    $response = $this->actingAs($regularUser, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/bulk/restore'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
            'file_ids' => [$file->id],
        ]);

    $response->assertForbidden();
});

it('validates bulk restore payload', function () {
    $lead = bulkLead();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/bulk/restore'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
        ]);

    $response->assertUnprocessable();
    assertHasValidationError($response, 'file_ids');
});

it('bulk restore only restores trashed files in mixed state', function () {
    $lead = bulkLead();
    $a = bulkFileFor($lead);
    $b = bulkFileFor($lead);

    $a->delete();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/bulk/restore'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
            'file_ids' => [$a->id, $b->id],
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.restored_count', 1);
    expect($a->fresh()->deleted_at)->toBeNull();
    expect($b->fresh()->deleted_at)->toBeNull();
});

it('bulk force-delete queues pending deletion and returns accurate count', function () {
    Storage::fake('s3');
    Queue::fake();

    $lead = bulkLead();
    $a = bulkFileFor($lead, ['disk' => 's3', 'storage_path' => 'files/a.txt']);
    $b = bulkFileFor($lead, ['disk' => 's3', 'storage_path' => 'files/b.txt']);

    Storage::disk('s3')->put('files/a.txt', 'a');
    Storage::disk('s3')->put('files/b.txt', 'b');

    $a->delete();
    $b->delete();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/bulk/force'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
            'file_ids' => [$a->id, $b->id],
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.force_deleted_count', 2);

    $pendingA = File::query()->withPendingDeletion()->withTrashed()->find($a->id);
    $pendingB = File::query()->withPendingDeletion()->withTrashed()->find($b->id);

    expect($pendingA)->not->toBeNull();
    expect($pendingB)->not->toBeNull();
    expect($pendingA?->pending_deletion_at)->not->toBeNull();
    expect($pendingB?->pending_deletion_at)->not->toBeNull();

    Storage::disk('s3')->assertExists((string) $a->storage_path);
    Storage::disk('s3')->assertExists((string) $b->storage_path);

    Queue::assertPushed(FinalizePendingFileDeletionJob::class, 2);
});

it('denies bulk force-delete for user without permissions', function () {
    Storage::fake('s3');

    $lead = bulkLead();
    $file = bulkFileFor($lead, ['disk' => 's3', 'storage_path' => 'files/denied.txt']);
    $file->delete();

    $regularUser = $this->createTenantUser();

    $response = $this->actingAs($regularUser, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/bulk/force'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
            'file_ids' => [$file->id],
        ]);

    $response->assertForbidden();
});

it('rejects bulk force-delete for non-existent file ids', function () {
    $lead = bulkLead();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/bulk/force'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
            'file_ids' => [(string) Str::ulid()],
        ]);

    $response->assertUnprocessable();
    assertHasValidationError($response, 'file_ids.0');
});

it('bulk force-deletes files even when storage objects are missing', function () {
    Storage::fake('s3');
    Queue::fake();

    $lead = bulkLead();
    $a = bulkFileFor($lead, ['disk' => 's3', 'storage_path' => 'files/missing-a.txt']);
    $b = bulkFileFor($lead, ['disk' => 's3', 'storage_path' => 'files/missing-b.txt']);

    $a->delete();
    $b->delete();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/bulk/force'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
            'file_ids' => [$a->id, $b->id],
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.force_deleted_count', 2);

    expect(File::query()->withPendingDeletion()->withTrashed()->find($a->id))->not->toBeNull();
    expect(File::query()->withPendingDeletion()->withTrashed()->find($b->id))->not->toBeNull();

    Storage::disk('s3')->assertMissing((string) $a->storage_path);
    Storage::disk('s3')->assertMissing((string) $b->storage_path);

    Queue::assertPushed(FinalizePendingFileDeletionJob::class, 2);
});

it('does not return pending-deletion files in files api responses', function () {
    Storage::fake('s3');
    Queue::fake();

    $lead = bulkLead();
    $pending = bulkFileFor($lead, ['disk' => 's3', 'storage_path' => 'files/pending.txt']);
    $visible = bulkFileFor($lead, ['name' => 'visible.txt']);

    Storage::disk('s3')->put((string) $pending->storage_path, 'pending');

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/bulk/force'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
            'file_ids' => [$pending->id],
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.force_deleted_count', 1);

    $listResponse = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('files?filter[fileable_type]=lead&filter[fileable_id]='.(string) $lead->id.'&filter[trashed]=with'));

    $listResponse->assertOk();

    $listedFileIds = collect($listResponse->json('data.files'))->pluck('id')->all();

    expect($listedFileIds)->toContain((string) $visible->id);
    expect($listedFileIds)->not->toContain((string) $pending->id);

    $downloadResponse = $this->actingAs($this->user, 'tenant-api')
        ->get($this->tenantApiUrl("files/{$pending->id}/download"));

    $downloadResponse->assertNotFound();
});

it('finalization job force-deletes pending files after deleting storage', function () {
    Storage::fake('s3');

    $lead = bulkLead();
    $file = bulkFileFor($lead, ['disk' => 's3', 'storage_path' => 'files/finalize.txt']);

    Storage::disk('s3')->put((string) $file->storage_path, 'content');

    $file->forceFill([
        'pending_deletion_at' => now(),
    ])->save();

    $job = new FinalizePendingFileDeletionJob(
        fileId: (string) $file->getKey(),
        actorId: (string) $this->user->getKey(),
        actorLabel: (string) $this->user->name,
        requestId: 'req_test_pending_cleanup',
    );

    $job->handle(app(ForceDeleteFile::class));

    expect(File::query()->withPendingDeletion()->withTrashed()->find($file->id))->toBeNull();
    Storage::disk('s3')->assertMissing((string) $file->storage_path);

    expect(ActivityEvent::query()
        ->where('subject_id', (string) $file->id)
        ->where('origin', 'job')
        ->where('actor_id', (string) $this->user->getKey())
        ->exists())->toBeTrue();
});
