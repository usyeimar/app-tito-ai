<?php

use App\Models\Tenant\Commons\Files\File;
use App\Models\Tenant\Commons\Files\FileFolder;
use App\Models\Tenant\CRM\Leads\Lead;

// ── Helpers ─────────────────────────────────────────────────────────────────

function listLead(): Lead
{
    return Lead::factory()->create();
}

function listFileFor(Lead $lead, array $attrs = []): File
{
    return File::factory()->forFileable($lead)->create($attrs);
}

function listFolderFor(Lead $lead, array $attrs = []): FileFolder
{
    return FileFolder::factory()->forFileable($lead)->create($attrs);
}

// ── Listing ─────────────────────────────────────────────────────────────────

it('lists files and folders for a fileable', function () {
    $lead = listLead();
    listFolderFor($lead, ['name' => 'docs']);
    listFileFor($lead, ['name' => 'readme.txt']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('files?filter[fileable_type]=lead&filter[fileable_id]='.$lead->id));

    $response->assertOk();
    $response->assertJsonPath('data.meta.item_count_total', 2);
    $response->assertJsonPath('data.meta.folder_count_total', 1);
    $response->assertJsonPath('data.meta.file_count_total', 1);
});

it('returns folders before files', function () {
    $lead = listLead();
    listFileFor($lead, ['name' => 'aaa-file.txt']);
    listFolderFor($lead, ['name' => 'zzz-folder']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('files?filter[fileable_type]=lead&filter[fileable_id]='.$lead->id));

    $response->assertOk();
    $items = $response->json('data.items');
    expect($items[0]['type'])->toBe('folder');
    expect($items[1]['type'])->toBe('file');
});

it('filters items inside a specific folder', function () {
    $lead = listLead();
    $folder = listFolderFor($lead, ['name' => 'docs']);
    listFileFor($lead, ['name' => 'root-file.txt']);
    listFileFor($lead, ['name' => 'nested-file.txt', 'folder_id' => $folder->id]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("files?filter[fileable_type]=lead&filter[fileable_id]={$lead->id}&filter[folder_id]={$folder->id}"));

    $response->assertOk();
    $response->assertJsonPath('data.meta.file_count_total', 1);
    $response->assertJsonPath('data.meta.current_folder_id', $folder->id);
});

it('supports search filter', function () {
    $lead = listLead();
    listFileFor($lead, ['name' => 'report.pdf']);
    listFileFor($lead, ['name' => 'photo.jpg']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("files?filter[fileable_type]=lead&filter[fileable_id]={$lead->id}&filter[search]=report"));

    $response->assertOk();
    $response->assertJsonPath('data.meta.file_count_total', 1);
});

// ── Cursor Pagination ───────────────────────────────────────────────────────

it('paginates with cursor', function () {
    $lead = listLead();

    // Create 3 files
    listFileFor($lead, ['name' => 'a.txt']);
    listFileFor($lead, ['name' => 'b.txt']);
    listFileFor($lead, ['name' => 'c.txt']);

    // First page of 2
    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("files?filter[fileable_type]=lead&filter[fileable_id]={$lead->id}&page[size]=2"));

    $response->assertOk();
    $response->assertJsonPath('data.meta.has_more', true);
    $response->assertJsonPath('data.meta.returned_count', 2);

    $firstPageIds = collect($response->json('data.items'))->pluck('resource.id')->all();

    $nextCursor = $response->json('data.meta.next_cursor');
    expect($nextCursor)->not->toBeNull();

    // Second page
    $response2 = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("files?filter[fileable_type]=lead&filter[fileable_id]={$lead->id}&page[size]=2&page[cursor]={$nextCursor}"));

    $response2->assertOk();
    $response2->assertJsonPath('data.meta.has_more', false);
    $response2->assertJsonPath('data.meta.returned_count', 1);

    $secondPageIds = collect($response2->json('data.items'))->pluck('resource.id')->all();
    expect(array_intersect($firstPageIds, $secondPageIds))->toBe([]);
});

// ── Trashed Filter ──────────────────────────────────────────────────────────

it('filters trashed items', function () {
    $lead = listLead();
    $active = listFileFor($lead, ['name' => 'active.txt']);
    $trashed = listFileFor($lead, ['name' => 'trashed.txt']);
    $trashed->delete();

    // Without trashed (default)
    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("files?filter[fileable_type]=lead&filter[fileable_id]={$lead->id}"));
    $response->assertJsonPath('data.meta.file_count_total', 1);

    // Only trashed
    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("files?filter[fileable_type]=lead&filter[fileable_id]={$lead->id}&filter[trashed]=only"));
    $response->assertJsonPath('data.meta.file_count_total', 1);

    // With trashed
    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("files?filter[fileable_type]=lead&filter[fileable_id]={$lead->id}&filter[trashed]=with"));
    $response->assertJsonPath('data.meta.file_count_total', 2);
});
