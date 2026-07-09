<?php

use App\Models\Customer;
use App\Models\RentalBlock;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_blocks', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
        });

        $names = RentalBlock::query()
            ->whereNotNull('customer_name')
            ->whereRaw("TRIM(customer_name) <> ''")
            ->distinct()
            ->pluck('customer_name');

        foreach ($names as $name) {
            $trimmed = trim((string) $name);
            if ($trimmed === '') {
                continue;
            }

            $customer = Customer::query()->firstOrCreate(
                ['name' => $trimmed],
                ['name' => $trimmed],
            );

            RentalBlock::query()
                ->where('customer_name', $name)
                ->whereNull('customer_id')
                ->update(['customer_id' => $customer->id]);
        }
    }

    public function down(): void
    {
        Schema::table('rental_blocks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
