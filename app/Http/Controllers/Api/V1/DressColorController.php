<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DressColor;
use App\Models\Product;
use App\Support\ProductColors;
use Illuminate\Http\Request;

class DressColorController extends Controller
{
    public function index()
    {
        return response()->json(
            DressColor::query()->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:dress_colors,name'],
            'hex' => ['required', 'string', 'size:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $validated['hex'] = ProductColors::normalizeHex($validated['hex']) ?? $validated['hex'];

        return response()->json(DressColor::query()->create($validated), 201);
    }

    public function update(Request $request, DressColor $dressColor)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:dress_colors,name,'.$dressColor->id],
            'hex' => ['required', 'string', 'size:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $previousName = $dressColor->name;
        $validated['hex'] = ProductColors::normalizeHex($validated['hex']) ?? $validated['hex'];
        $dressColor->update($validated);

        if ($previousName !== $dressColor->name) {
            Product::query()
                ->whereJsonContains('colors', $previousName)
                ->get()
                ->each(function (Product $product) use ($previousName, $dressColor): void {
                    $colors = collect($product->colors ?? [])
                        ->map(fn ($name) => $name === $previousName ? $dressColor->name : $name)
                        ->filter(fn ($name) => is_string($name) && trim($name) !== '')
                        ->unique()
                        ->values()
                        ->all();

                    $product->update(['colors' => $colors]);
                });
        }

        return response()->json($dressColor->fresh());
    }

    public function destroy(DressColor $dressColor)
    {
        $inUse = Product::query()
            ->whereJsonContains('colors', $dressColor->name)
            ->exists();

        if ($inUse) {
            return response()->json([
                'message' => 'Color is in use by products and cannot be deleted.',
            ], 422);
        }

        $dressColor->delete();

        return response()->noContent();
    }
}
