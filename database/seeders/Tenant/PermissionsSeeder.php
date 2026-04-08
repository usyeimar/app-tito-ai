<?php

namespace Database\Seeders\Tenant;

use App\Models\Central\Auth\Role\Permission;
use App\Models\Central\Auth\Role\Role;
use App\Support\Permissions\TenantPermissionRegistry;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionDefinitions = collect(TenantPermissionRegistry::permissionDefinitions())
            ->unique('name')
            ->values();

        $permissionNames = $permissionDefinitions->pluck('name')->all();

        Permission::query()
            ->where('guard_name', 'tenant')
            ->whereNotIn('name', $permissionNames)
            ->delete();

        $permissions = $permissionDefinitions
            ->map(function (array $definition): Permission {
                return Permission::query()->updateOrCreate(
                    [
                        'name' => $definition['name'],
                        'guard_name' => 'tenant',
                    ],
                    []
                );
            });

        $superAdminRole = Role::query()->firstOrCreate([
            'name' => 'super_admin',
            'guard_name' => 'tenant',
        ]);

        $adminRole = Role::query()->firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'tenant',
        ]);

        $userRole = Role::query()->firstOrCreate([
            'name' => 'user',
            'guard_name' => 'tenant',
        ]);

        $superAdminRole->syncPermissions($permissions);

        $adminPermissionNames = $permissionDefinitions
            ->filter(fn (array $definition): bool => $definition['action'] !== 'delete')
            ->pluck('name')
            ->all();

        $adminPermissions = Permission::query()
            ->where('guard_name', 'tenant')
            ->whereIn('name', $adminPermissionNames)
            ->get();

        $adminRole->syncPermissions($adminPermissions);

        $userPermissionNames = $permissionDefinitions
            ->filter(fn (array $definition): bool => $definition['action'] === 'view')
            ->pluck('name')
            ->all();

        $userPermissions = Permission::query()
            ->where('guard_name', 'tenant')
            ->whereIn('name', $userPermissionNames)
            ->get();

        $userRole->syncPermissions($userPermissions);
    }
}
