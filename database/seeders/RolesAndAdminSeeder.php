<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RolesAndAdminSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::findOrCreate('admin');
        $buyerRole = Role::findOrCreate('buyer');

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@bodega.test'],
            [
                'name' => 'Bodega Admin',
                'password' => Hash::make('password123'),
            ]
        );
        $admin->assignRole($adminRole);

        $buyer = User::query()->firstOrCreate(
            ['email' => 'buyer@bodega.test'],
            [
                'name' => 'Bodega Buyer',
                'password' => Hash::make('password123'),
            ]
        );
        $buyer->assignRole($buyerRole);

        Product::factory()->count(12)->create();
    }
}
