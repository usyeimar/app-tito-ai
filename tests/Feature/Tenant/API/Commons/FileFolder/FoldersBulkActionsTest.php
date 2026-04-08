<?php

use App\Models\Tenant\Commons\Files\FileFolder;
use App\Models\Tenant\CRM\Leads\Lead;

// ── Helpers ─────────────────────────────────────────────────────────────────

function bulkFolderLead(): Lead
{
    $lead = Lead::factory()->make();
    $lead->save();

    return $lead;
}

function bulkFolder(Lead $lead, array $attrs = []): FileFolder
{
    return FileFolder::factory()->forFileable($lead)->create($attrs);
}

// ── Bulk Move ───────────────────────────────────────────────────────────────

it('bulk moves folders to a destination', function () {
    $lead = bulkFolderLead();
    $dest = bulkFolder($lead, ['name' => 'dest']);
    $a = bulkFolder($lead, ['name' => 'a']);
    $b = bulkFolder($lead, ['name' => 'b']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/folders/bulk/move'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
            'folder_ids' => [$a->id, $b->id],
            'destination_parent_id' => $dest->id,
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.moved_count', 2);
    expect($a->fresh()->parent_id)->toBe($dest->id);
    expect($b->fresh()->parent_id)->toBe($dest->id);
});

// ── Bulk Delete ─────────────────────────────────────────────────────────────

it('bulk soft-deletes folders with accurate count', function () {
    $lead = bulkFolderLead();
    $a = bulkFolder($lead, ['name' => 'a']);
    $b = bulkFolder($lead, ['name' => 'b']);
    $c = bulkFolder($lead, ['name' => 'c']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/folders/bulk/delete'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
            'folder_ids' => [$a->id, $b->id, $c->id],
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.deleted_count', 3);
    expect(FileFolder::find($a->id))->toBeNull();
    expect(FileFolder::find($b->id))->toBeNull();
    expect(FileFolder::find($c->id))->toBeNull();
});

it('bulk soft-deletes only active folders when request mixes trashed entries', function () {
    $lead = bulkFolderLead();
    $a = bulkFolder($lead, ['name' => 'a']);
    $b = bulkFolder($lead, ['name' => 'b']);
    $c = bulkFolder($lead, ['name' => 'c']);

    $b->delete();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/folders/bulk/delete'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
            'folder_ids' => [$a->id, $b->id, $c->id],
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.deleted_count', 2);

    expect(FileFolder::find($a->id))->toBeNull();
    expect(FileFolder::find($b->id))->toBeNull();
    expect(FileFolder::find($c->id))->toBeNull();

    $trashedB = FileFolder::withTrashed()->find($b->id);
    expect($trashedB)->not->toBeNull();
    expect($trashedB?->trashed())->toBeTrue();
});

// ── Bulk Restore ────────────────────────────────────────────────────────────

it('bulk restores folders with accurate count', function () {
    $lead = bulkFolderLead();
    $a = bulkFolder($lead, ['name' => 'a']);
    $b = bulkFolder($lead, ['name' => 'b']);

    $a->delete();
    $b->delete();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/folders/bulk/restore'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
            'folder_ids' => [$a->id, $b->id],
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.restored_count', 2);
    expect($a->fresh()->deleted_at)->toBeNull();
    expect($b->fresh()->deleted_at)->toBeNull();
});

// ── Bulk Force Delete ───────────────────────────────────────────────────────

it('bulk force-deletes folders with accurate count', function () {
    $lead = bulkFolderLead();
    $a = bulkFolder($lead, ['name' => 'a']);
    $b = bulkFolder($lead, ['name' => 'b']);

    $a->delete();
    $b->delete();

    $response = $this->actingAs($this->user, 'tenant-api')
        ->postJson($this->tenantApiUrl('files/folders/bulk/force'), [
            'fileable_type' => 'lead',
            'fileable_id' => $lead->id,
            'folder_ids' => [$a->id, $b->id],
        ]);

    $response->assertOk();
    $response->assertJsonPath('data.force_deleted_count', 2);
    expect(FileFolder::withTrashed()->find($a->id))->toBeNull();
    expect(FileFolder::withTrashed()->find($b->id))->toBeNull();
});
