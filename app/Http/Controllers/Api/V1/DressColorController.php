<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DressColor;
use App\Models\Product;
use App\Support\ProductColors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'image' => ['nullable', 'file', 'image', 'max:2048'],
        ]);

        $validated['hex'] = ProductColors::normalizeHex($validated['hex']) ?? $validated['hex'];

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('dress-colors', 'public');
            $validated['image_url'] = Storage::disk('public')->url($path);
        }

        unset($validated['image']);

        return response()->json(DressColor::query()->create($validated), 201);
    }

    public function update(Request $request, DressColor $dressColor)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:dress_colors,name,'.$dressColor->id],
            'hex' => ['required', 'string', 'size:7', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'image' => ['nullable', 'file', 'image', 'max:2048'],
            'remove_image' => ['nullable', 'in:1,true'],
        ]);

        $previousName = $dressColor->name;
        $validated['hex'] = ProductColors::normalizeHex($validated['hex']) ?? $validated['hex'];

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('dress-colors', 'public');
            $validated['image_url'] = Storage::disk('public')->url($path);
        } elseif ($request->input('remove_image')) {
            $validated['image_url'] = null;
        }

        unset($validated['image'], $validated['remove_image']);

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
