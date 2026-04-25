<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Role::findOrCreate('customer');
    }

    public function down(): void
    {
        Role::query()->where('name', 'customer')->delete();
    }
};
