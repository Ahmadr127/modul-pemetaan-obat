<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Create Permissions
        $permissions = [
            ['name' => 'manage_roles', 'display_name' => 'Kelola Roles', 'description' => 'Mengelola roles dan permissions'],
            ['name' => 'manage_permissions', 'display_name' => 'Kelola Permissions', 'description' => 'Mengelola permissions'],
            ['name' => 'view_dashboard', 'display_name' => 'Lihat Dashboard', 'description' => 'Melihat halaman dashboard'],
            ['name' => 'manage_users', 'display_name' => 'Kelola Users', 'description' => 'Mengelola pengguna'],
            ['name' => 'manage_organization_types', 'display_name' => 'Kelola Tipe Organisasi', 'description' => 'Mengelola tipe organisasi'],
            ['name' => 'manage_organization_units', 'display_name' => 'Kelola Unit Organisasi', 'description' => 'Mengelola unit organisasi'],
            ['name' => 'manage_pemetaan_obat', 'display_name' => 'Kelola Pemetaan Obat', 'description' => 'Mengelola pemetaan obat generik dan brand'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name']],
                $permission
            );
        }

        // Create Roles
        $adminRole = Role::updateOrCreate(
            ['name' => 'admin'],
            [
                'display_name' => 'Administrator',
                'description' => 'Role dengan akses penuh ke sistem'
            ]
        );

        $userRole = Role::updateOrCreate(
            ['name' => 'user'],
            [
                'display_name' => 'Pengguna',
                'description' => 'Role untuk pengguna umum'
            ]
        );

        // Assign permissions to roles
        $adminRole->permissions()->syncWithoutDetaching(Permission::all());

        $userRole->permissions()->syncWithoutDetaching(
            Permission::whereIn('name', [
                'view_dashboard'
            ])->get()
        );
    }
}