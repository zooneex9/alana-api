<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->after('price');
            $table->json('payment_plans')->nullable()->after('item_condition');
        });

        $rows = DB::table('products')->get();

        foreach ($rows as $row) {
            $row = (object) (array) $row;
            $plans = [];
            $ptype = $row->payment_type ?? 'full';
            if ($ptype === 'installment') {
                $plans[] = [
                    'type' => 'installment',
                    'down_payment' => (float) ($row->down_payment ?? 0),
                    'installments' => (int) ($row->installments ?? 1),
                ];
            } else {
                $plans[] = ['type' => 'full'];
            }
            DB::table('products')->where('id', $row->id)->update([
                'payment_plans' => json_encode($plans),
                'quantity' => 1,
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['payment_type', 'down_payment', 'installments']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('payment_type', 20)->default('full');
            $table->decimal('down_payment', 12, 2)->nullable();
            $table->unsignedTinyInteger('installments')->nullable();
        });

        $rows = DB::table('products')->get();
        foreach ($rows as $row) {
            $row = (object) (array) $row;
            $legacy = 'full';
            $down = null;
            $inst = null;
            $raw = $row->payment_plans ?? '[]';
            $plans = is_string($raw) ? json_decode($raw, true) : (array) $raw;
            $first = $plans[0] ?? null;
            if (is_array($first) && ($first['type'] ?? '') === 'installment') {
                $legacy = 'installment';
                $down = $first['down_payment'] ?? 0;
                $inst = $first['installments'] ?? 1;
            }
            DB::table('products')->where('id', $row->id)->update([
                'payment_type' => $legacy,
                'down_payment' => $down,
                'installments' => $inst,
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'payment_plans']);
        });
    }
};
