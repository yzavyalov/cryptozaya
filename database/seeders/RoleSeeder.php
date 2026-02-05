<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // --- 1. Список прав ---
        $permissions = [
            'view dashboard',
            'manage users',
            'manage merchants',
            'manage partners',
            'manage transactions',
            'view reports',
            'edit settings',
            'create token',
            'update token',
        ];

        // --- 2. Создание прав (если их нет) ---
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // --- 3. Список ролей и их права ---
        $roles = [
            'user' => ['view dashboard'],
            'partner' => ['view dashboard', 'view reports'],
            'merchant' => ['view dashboard', 'manage transactions', 'view reports','manage merchants','create token', 'update token'],
            'admin' => ['view dashboard', 'manage users', 'manage merchants','manage partners','manage transactions', 'view reports', 'edit settings','create token', 'update token'],
        ];

        // --- 4. Создание ролей и назначение прав ---
        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePermissions);
        }

        echo "✅ Roles and permissions have been successfully seeded.\n";
    }
}

