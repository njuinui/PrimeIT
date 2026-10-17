<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\Permissions\Catalog;
use App\Support\Permissions\RolesMatrix;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (['web', 'sanctum'] as $guard) {
            foreach (Catalog::groups() as $permissions) {
                foreach (array_keys($permissions) as $name) {
                    Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]);
                }
            }

            foreach (RolesMatrix::roles() as $definition) {
                $role = Role::firstOrCreate(
                    ['name' => $definition['name'], 'guard_name' => $guard],
                    ['description' => $definition['description'], 'is_system' => $definition['is_system']],
                );

                if (! $role->wasRecentlyCreated) {
                    $role->forceFill([
                        'description' => $definition['description'],
                        'is_system'   => $definition['is_system'],
                    ])->save();
                }

                $role->syncPermissions(
                    $definition['permissions'] === ['*']
                        ? Catalog::all()
                        : $definition['permissions'],
                );
            }
        }
    }
}