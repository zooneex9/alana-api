<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $adminRole = Role::findOrCreate('admin');

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@alana.com'],
            [
                'name' => 'Alana Admin',
                'password' => Hash::make('alana2026'),
            ]
        );

        if (! $admin->hasRole($adminRole)) {
            $admin->assignRole($adminRole);
        }
    }

    public function down(): void
    {
        User::query()
            ->where('email', 'admin@alana.com')
            ->delete();
    }
};
