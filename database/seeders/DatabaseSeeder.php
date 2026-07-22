<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        // Ensure the bootstrap admin exists and holds the admin role.
        $admin = User::updateOrCreate(
            ['email' => 'admin@gnd.test'],
            ['name' => 'Admin', 'password' => Hash::make('password'), 'is_active' => true],
        );
        $admin->syncRoles('admin');

        $this->call(DemoSeeder::class);
        $this->call(ShowcasePartnerSeeder::class);
    }
}
