<?php

namespace Database\Seeders\Central;

use App\Models\Central\Auth\Role\Permission;
use App\Models\Central\Auth\Role\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class CentralPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionNames = config('central_permissions.permissions', []);

        Permission::query()
            ->where('guard_name', 'web')
            ->whereNotIn('name', $permissionNames)
            ->delete();

        $permissions = collect($permissionNames)
            ->map(fn (string $name): Permission => Permission::query()->updateOrCreate(
                [
                    'name' => $name,
                    'guard_name' => 'web',
                ],
                []
            ));

        $superAdminRole = Role::query()->firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        $adminRole = Role::query()->firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $supportRole = Role::query()->firstOrCreate([
            'name' => 'support',
            'guard_name' => 'web',
        ]);

        $superAdminRole->syncPermissions($permissions);

        $adminPermissions = $permissions
            ->reject(fn (Permission $permission): bool => in_array($permission->name, ['support.impersonate', 'audit.view'], true));
        $adminRole->syncPermissions($adminPermissions);

        $supportPermissions = $permissions
            ->filter(fn (Permission $permission): bool => in_array($permission->name, ['support.impersonate', 'audit.view'], true));
        $supportRole->syncPermissions($supportPermissions);
    }
}
