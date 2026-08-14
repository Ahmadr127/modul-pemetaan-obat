<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Hanya role admin & user beserta permission-nya
        $this->call([
            RolePermissionSeeder::class,
        ]);

        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        $userRole = \App\Models\Role::where('name', 'user')->first();

        // Akun admin
        User::factory()->create([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'role_id' => $adminRole->id,
            'password' => bcrypt('rsazra'),
        ]);

        // Akun user biasa
        User::factory()->create([
            'name' => 'Pengguna Biasa',
            'username' => 'user',
            'email' => 'user@example.com',
            'role_id' => $userRole->id,
            'password' => bcrypt('rsazra'),
        ]);
    }
}
