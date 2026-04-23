<?php

use App\Models\Central\Auth\Role\Permission;
use App\Models\Tenant\CRM\Companies\Company;
use Illuminate\Support\Facades\Storage;

it('returns the profile picture when the user can view the owning entity', function () {
    config()->set('filesystems.default', 'local');
    Storage::fake('local');

    $company = Company::factory()->make();
    $company->save();

    $profilePicture = $company->profilePicture()->create([
        'path' => 'profile-pictures/company.webp',
    ]);

    Storage::disk('local')->put($profilePicture->path, 'webp-content');

    $response = $this->actingAs($this->user, 'tenant-api')
        ->get($this->tenantApiUrl("entity-profile-pictures/{$profilePicture->id}"));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/webp');
})->skip('CRM companies migration not yet available');

it('denies profile picture access when user cannot view the owning entity', function () {
    config()->set('filesystems.default', 'local');
    Storage::fake('local');

    $viewer = $this->createTenantUser();

    foreach (['company.view', 'company.manage', 'company.delete'] as $permissionName) {
        $permissionModel = Permission::query()->firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'tenant',
        ]);

        $viewer->givePermissionTo($permissionModel);
    }

    $viewer->revokePermissionTo('company.view');

    $company = Company::factory()->make();
    $company->save();

    $profilePicture = $company->profilePicture()->create([
        'path' => 'profile-pictures/company-private.webp',
    ]);

    Storage::disk('local')->put($profilePicture->path, 'webp-content');

    $response = $this->actingAs($viewer, 'tenant-api')
        ->get($this->tenantApiUrl("entity-profile-pictures/{$profilePicture->id}"));

    $response->assertForbidden();
})->skip('CRM companies migration not yet available');
