<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('dress_length', 16)->nullable()->after('category');
            $table->json('occasions')->nullable()->after('dress_length');
            $table->boolean('is_vintage')->default(false)->after('occasions');
            $table->boolean('is_new_arrival')->default(false)->after('is_vintage');
            $table->boolean('is_dr_fave')->default(false)->after('is_new_arrival');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'dress_length',
                'occasions',
                'is_vintage',
                'is_new_arrival',
                'is_dr_fave',
            ]);
        });
    }
};
