<?php

namespace App\Support\Permissions;

class TenantPermissionRegistry
{
    private const array ACTIONS = [
        'view' => [
            'label' => 'View',
            'destructive' => false,
            'management' => false,
        ],
        'manage' => [
            'label' => 'Manage',
            'destructive' => false,
            'management' => true,
        ],
        'delete' => [
            'label' => 'Delete',
            'destructive' => true,
            'management' => false,
        ],
    ];

    private const array DEFAULT_ACTIONS = ['view', 'manage', 'delete'];

    private const array MODULES = [
        ['key' => 'user', 'label' => 'Users'],
        ['key' => 'invitation', 'label' => 'Invitations'],
        ['key' => 'company', 'label' => 'Companies'],
        ['key' => 'property', 'label' => 'Properties'],
        ['key' => 'contact', 'label' => 'Contacts'],
        ['key' => 'lead', 'label' => 'Leads'],
        ['key' => 'activity', 'label' => 'Activity'],
        ['key' => 'vendor_company', 'label' => 'Vendor Companies'],
        ['key' => 'project', 'label' => 'Projects'],
        ['key' => 'agent', 'label' => 'Agents'],
        ['key' => 'trunk', 'label' => 'Trunks'],
        ['key' => 'knowledge_base', 'label' => 'Knowledge Bases'],
        ['key' => 'file', 'label' => 'Files'],
        ['key' => 'document_signing', 'label' => 'Document Signing'],
        ['key' => 'tenant', 'label' => 'Tenant'],
        ['key' => 'metadata', 'label' => 'Metadata'],
        ['key' => 'system_configurations', 'label' => 'System Configurations'],
    ];

    /**
     * @return array<int, array{key:string,label:string,permissions:array<int, array{name:string,label:string,action:string,destructive:bool,management:bool}>}>
     */
    public static function modules(): array
    {
        return array_map(function (array $module): array {
            $permissions = [];
            $actions = $module['actions'] ?? self::DEFAULT_ACTIONS;

            foreach ($actions as $action) {
                $meta = self::ACTIONS[$action] ?? null;
                if (! $meta) {
                    continue;
                }

                $permissions[] = [
                    'name' => self::name($module['key'], $action),
                    'label' => $meta['label'],
                    'action' => $action,
                    'destructive' => $meta['destructive'],
                    'management' => $meta['management'],
                ];
            }

            return [
                'key' => $module['key'],
                'label' => $module['label'],
                'permissions' => $permissions,
            ];
        }, self::MODULES);
    }

    /**
     * @return array<int, array{name:string,label:string,action:string,destructive:bool,management:bool,module:string}>
     */
    public static function permissionDefinitions(): array
    {
        $definitions = [];

        foreach (self::modules() as $module) {
            foreach ($module['permissions'] as $permission) {
                $definitions[] = array_merge($permission, [
                    'module' => $module['key'],
                ]);
            }
        }

        return $definitions;
    }

    /**
     * @return array<int, string>
     */
    public static function permissionNames(): array
    {
        return array_map(
            fn (array $permission): string => $permission['name'],
            self::permissionDefinitions(),
        );
    }

    public static function name(string $module, string $action): string
    {
        return "{$module}.{$action}";
    }
}
