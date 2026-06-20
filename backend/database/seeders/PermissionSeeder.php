<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'wallet.view',
            'wallet.create',
            'wallet.activate',
            'wallet.freeze',
            'wallet.unfreeze',
            'wallet.restrict',
            'wallet.unrestrict',
            'wallet.close',
            'wallet.recharge',
            'wallet.transaction.view',
            'wallet.statistics.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions($permissions);

        $distributorRole = Role::firstOrCreate(['name' => 'distributor', 'guard_name' => 'web']);
        $distributorRole->syncPermissions([
            'wallet.view',
            'wallet.transaction.view',
            'wallet.statistics.view',
        ]);
    }
}
