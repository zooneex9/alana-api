<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $adminRole = Role::findOrCreate('admin');

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@bodega.com'],
            [
                'name' => 'Bodega Admin',
                'password' => 'bodega123',
            ]
        );

        if (! $admin->hasRole($adminRole)) {
            $admin->assignRole($adminRole);
        }
    }

    public function down(): void
    {
        User::query()
            ->where('email', 'admin@bodega.com')
            ->delete();
    }
};
