<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Safe to run in production: creates roles/permissions only, never users or data. */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(Permissions::ALL) as $name) {
            Permission::findOrCreate($name, 'web');
        }
        Role::findOrCreate(Permissions::SUPER_ADMIN, 'web');
        Role::findOrCreate(Permissions::AFFILIATE, 'web');
        foreach (Permissions::ROLE_DEFAULTS as $role => $permissions) {
            $r = Role::findOrCreate($role, 'web');
            if ($r->permissions()->count() === 0) {
                $r->syncPermissions($permissions);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
