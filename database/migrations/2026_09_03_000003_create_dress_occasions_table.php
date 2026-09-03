<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dress_occasions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });

        $now = now();
        DB::table('dress_occasions')->insert([
            ['name' => 'Anillo / civil', 'slug' => 'anillo_civil', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Night out', 'slug' => 'night_out', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Boda en playa', 'slug' => 'boda_playa', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Boda', 'slug' => 'boda', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Viaje a la playa', 'slug' => 'viaje_playa', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Posada', 'slug' => 'posada', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('dress_occasions');
    }
};
