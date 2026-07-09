<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('size', 32)->nullable()->after('category');
            $table->string('color', 64)->nullable()->after('size');
            $table->decimal('rental_price_daily', 12, 2)->nullable()->after('price');
            $table->decimal('rental_price_weekend', 12, 2)->nullable()->after('rental_price_daily');
            $table->decimal('deposit', 12, 2)->nullable()->after('rental_price_weekend');
        });

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $this->rebuildProductsTableForSqlite();
        } else {
            DB::table('products')->where('status', 'separated')->update(['status' => 'reserved']);
            DB::table('products')->where('status', 'sold')->update(['status' => 'rented']);
            DB::statement("ALTER TABLE products MODIFY status ENUM('available', 'reserved', 'rented') NOT NULL DEFAULT 'available'");
        }

        DB::table('products')
            ->whereNull('rental_price_daily')
            ->update(['rental_price_daily' => DB::raw('price')]);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // Best-effort: map statuses back before dropping rental columns.
            DB::table('products')->where('status', 'reserved')->update(['status' => 'separated']);
            DB::table('products')->where('status', 'rented')->update(['status' => 'sold']);
        } else {
            DB::table('products')->where('status', 'reserved')->update(['status' => 'separated']);
            DB::table('products')->where('status', 'rented')->update(['status' => 'sold']);
            DB::statement("ALTER TABLE products MODIFY status ENUM('available', 'separated', 'sold') NOT NULL DEFAULT 'available'");
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['size', 'color', 'rental_price_daily', 'rental_price_weekend', 'deposit']);
        });
    }

    private function rebuildProductsTableForSqlite(): void
    {
        Schema::rename('products', 'products_legacy');

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->decimal('price', 12, 2);
            $table->decimal('rental_price_daily', 12, 2)->nullable();
            $table->decimal('rental_price_weekend', 12, 2)->nullable();
            $table->decimal('deposit', 12, 2)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status')->default('available');
            $table->string('category');
            $table->string('size', 32)->nullable();
            $table->string('color', 64)->nullable();
            $table->string('item_condition')->nullable();
            $table->boolean('shipping_to_agree')->default(false);
            $table->date('date_added')->nullable();
            $table->json('images')->nullable();
            $table->json('payment_plans')->nullable();
            $table->timestamps();
        });

        DB::statement("
            INSERT INTO products (
                id, name, description, price, rental_price_daily, rental_price_weekend, deposit,
                quantity, status, category, size, color, item_condition, shipping_to_agree,
                date_added, images, payment_plans, created_at, updated_at
            )
            SELECT
                id, name, description, price, rental_price_daily, rental_price_weekend, deposit,
                quantity,
                CASE status
                    WHEN 'separated' THEN 'reserved'
                    WHEN 'sold' THEN 'rented'
                    ELSE status
                END,
                category, size, color, item_condition, shipping_to_agree,
                date_added, images, payment_plans, created_at, updated_at
            FROM products_legacy
        ");

        Schema::drop('products_legacy');
    }
};
