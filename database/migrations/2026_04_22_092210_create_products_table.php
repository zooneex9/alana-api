<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->decimal('price', 12, 2);
            $table->enum('status', ['available', 'separated', 'sold'])->default('available');
            $table->enum('payment_type', ['full', 'installment'])->default('full');
            $table->decimal('down_payment', 12, 2)->nullable();
            $table->unsignedTinyInteger('installments')->nullable();
            $table->string('category');
            $table->date('date_added')->nullable();
            $table->string('image_path')->nullable();
            $table->text('image_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
