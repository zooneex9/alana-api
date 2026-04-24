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
            $table->json('images')->nullable()->after('date_added');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            $rows = DB::table('products')->select('id', 'image_path', 'image_url')->get();
        } else {
            $rows = DB::table('products')->select('id', 'image_path', 'image_url')->get();
        }

        foreach ($rows as $row) {
            $images = [];
            if (filled($row->image_path) || filled($row->image_url)) {
                $images[] = [
                    'path' => $row->image_path ? (string) $row->image_path : null,
                    'url' => $row->image_url ? (string) $row->image_url : null,
                ];
            }
            DB::table('products')->where('id', $row->id)->update([
                'images' => empty($images) ? null : json_encode($images),
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'image_url']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image_path')->nullable();
            $table->text('image_url')->nullable();
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            $rows = DB::table('products')->select('id', 'images')->get();
        } else {
            $rows = DB::table('products')->select('id', 'images')->get();
        }

        foreach ($rows as $row) {
            $imagePath = null;
            $imageUrl = null;
            $raw = $row->images;
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
            } else {
                $decoded = is_array($raw) ? $raw : null;
            }
            if (is_array($decoded) && $decoded !== []) {
                $first = $decoded[0];
                if (is_array($first)) {
                    $imagePath = $first['path'] ?? null;
                    $imageUrl = $first['url'] ?? null;
                }
            }
            DB::table('products')->where('id', $row->id)->update([
                'image_path' => $imagePath,
                'image_url' => $imageUrl,
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('images');
        });
    }
};
