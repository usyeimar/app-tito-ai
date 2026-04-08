<?php

use App\Models\Tenant\Commons\Files\FileFolder;
use App\Models\Tenant\CRM\Leads\Lead;
use Illuminate\Support\Str;

// ── Helpers ─────────────────────────────────────────────────────────────────

function treeLead(): Lead
{
    $lead = Lead::factory()->make();
    $lead->save();

    return $lead;
}

function treeFolder(Lead $lead, array $attrs = []): FileFolder
{
    $factory = FileFolder::factory()->forFileable($lead);

    if (isset($attrs['parent_id'])) {
        $parent = FileFolder::query()->findOrFail($attrs['parent_id']);
        $factory = $factory->inFolder($parent);
        unset($attrs['parent_id']);
    }

    return $factory->create($attrs);
}

// ── Tree ────────────────────────────────────────────────────────────────────

it('returns folder tree for a fileable', function () {
    $lead = treeLead();
    $root = treeFolder($lead, ['name' => 'root']);
    $child = treeFolder($lead, ['name' => 'child', 'parent_id' => $root->id]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("files/tree?filter[fileable_type]=lead&filter[fileable_id]={$lead->id}"));

    $response->assertOk();
    $tree = $response->json('data.tree');

    expect($tree)->toHaveCount(1);
    expect($tree[0]['folder']['name'])->toBe('root');
    expect($tree[0]['children'])->toHaveCount(1);
    expect($tree[0]['children'][0]['folder']['name'])->toBe('child');
});

it('respects depth limit', function () {
    $lead = treeLead();
    $l1 = treeFolder($lead, ['name' => 'level-1']);
    $l2 = treeFolder($lead, ['name' => 'level-2', 'parent_id' => $l1->id]);
    $l3 = treeFolder($lead, ['name' => 'level-3', 'parent_id' => $l2->id]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("files/tree?filter[fileable_type]=lead&filter[fileable_id]={$lead->id}&filter[depth]=1"));

    $response->assertOk();
    $tree = $response->json('data.tree');

    expect($tree)->toHaveCount(1);
    expect($tree[0]['children'])->toBeEmpty();
    $response->assertJsonPath('data.meta.depth', 1);
});

it('returns tree from a specific parent', function () {
    $lead = treeLead();
    $root = treeFolder($lead, ['name' => 'root']);
    $child1 = treeFolder($lead, ['name' => 'child-1', 'parent_id' => $root->id]);
    $child2 = treeFolder($lead, ['name' => 'child-2', 'parent_id' => $root->id]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("files/tree?filter[fileable_type]=lead&filter[fileable_id]={$lead->id}&filter[parent_id]={$root->id}"));

    $response->assertOk();
    $tree = $response->json('data.tree');

    expect($tree)->toHaveCount(2);
});

it('returns hierarchy validation error when parent does not belong to fileable', function () {
    $lead = treeLead();
    $otherLead = treeLead();
    $foreignParent = treeFolder($otherLead, ['name' => 'foreign-parent']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("files/tree?filter[fileable_type]=lead&filter[fileable_id]={$lead->id}&filter[parent_id]={$foreignParent->id}"));

    $response->assertStatus(422);
    $response->assertJsonPath('errors.0.code', 'COMMONS_FILE_HIERARCHY_INVALID');
});

it('sorts tree nodes alphabetically', function () {
    $lead = treeLead();
    treeFolder($lead, ['name' => 'zebra']);
    treeFolder($lead, ['name' => 'alpha']);
    treeFolder($lead, ['name' => 'middle']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("files/tree?filter[fileable_type]=lead&filter[fileable_id]={$lead->id}"));

    $response->assertOk();
    $names = array_map(fn ($node) => $node['folder']['name'], $response->json('data.tree'));
    expect($names)->toBe(['alpha', 'middle', 'zebra']);
});

it('supports with and only trashed filters for tree', function () {
    $lead = treeLead();
    $active = treeFolder($lead, ['name' => 'active']);
    $trashed = treeFolder($lead, ['name' => 'trashed']);

    $trashed->delete();

    $withResponse = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("files/tree?filter[fileable_type]=lead&filter[fileable_id]={$lead->id}&filter[trashed]=with"));

    $withResponse->assertOk();
    $withTreeIds = collect($withResponse->json('data.tree'))->pluck('folder.id')->all();
    expect($withTreeIds)->toContain((string) $active->id);
    expect($withTreeIds)->toContain((string) $trashed->id);

    $onlyResponse = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("files/tree?filter[fileable_type]=lead&filter[fileable_id]={$lead->id}&filter[trashed]=only"));

    $onlyResponse->assertOk();
    $onlyTreeIds = collect($onlyResponse->json('data.tree'))->pluck('folder.id')->all();
    expect($onlyTreeIds)->toHaveCount(1);
    expect($onlyTreeIds)->toContain((string) $trashed->id);
    expect($onlyTreeIds)->not->toContain((string) $active->id);
});

// ── Breadcrumbs ─────────────────────────────────────────────────────────────

it('returns breadcrumbs from root to current folder', function () {
    $lead = treeLead();
    $root = treeFolder($lead, ['name' => 'root']);
    $child = treeFolder($lead, ['name' => 'child', 'parent_id' => $root->id]);
    $grandchild = treeFolder($lead, ['name' => 'grandchild', 'parent_id' => $child->id]);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("files/folders/{$grandchild->id}/breadcrumbs"));

    $response->assertOk();
    $breadcrumbs = $response->json('data.breadcrumbs');

    expect($breadcrumbs)->toHaveCount(3);
    expect($breadcrumbs[0]['name'])->toBe('root');
    expect($breadcrumbs[1]['name'])->toBe('child');
    expect($breadcrumbs[2]['name'])->toBe('grandchild');
});

it('returns single breadcrumb for root folder', function () {
    $lead = treeLead();
    $root = treeFolder($lead, ['name' => 'root']);

    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl("files/folders/{$root->id}/breadcrumbs"));

    $response->assertOk();
    $breadcrumbs = $response->json('data.breadcrumbs');

    expect($breadcrumbs)->toHaveCount(1);
    expect($breadcrumbs[0]['name'])->toBe('root');
});

it('returns not found for breadcrumbs when folder id is unknown', function () {
    $response = $this->actingAs($this->user, 'tenant-api')
        ->getJson($this->tenantApiUrl('files/folders/'.Str::ulid().'/breadcrumbs'));

    $response->assertNotFound();
});
