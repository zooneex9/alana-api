<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DressOccasion;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DressOccasionController extends Controller
{
    public function index()
    {
        return response()->json(
            DressOccasion::query()->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:dress_occasions,name'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:dress_occasions,slug', 'regex:/^[a-z0-9_]+$/'],
        ]);

        $validated['slug'] = $validated['slug'] ?? DressOccasion::makeSlug($validated['name']);
        $validated['slug'] = $this->uniqueSlug($validated['slug']);

        return response()->json(DressOccasion::query()->create($validated), 201);
    }

    public function update(Request $request, DressOccasion $dressOccasion)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('dress_occasions', 'name')->ignore($dressOccasion->id)],
            'slug' => ['nullable', 'string', 'max:100', Rule::unique('dress_occasions', 'slug')->ignore($dressOccasion->id), 'regex:/^[a-z0-9_]+$/'],
        ]);

        $previousSlug = $dressOccasion->slug;
        if (! empty($validated['slug'])) {
            $validated['slug'] = $this->uniqueSlug($validated['slug'], $dressOccasion->id);
        } else {
            unset($validated['slug']);
        }

        $dressOccasion->update($validated);

        if ($previousSlug !== $dressOccasion->slug) {
            Product::query()
                ->whereJsonContains('occasions', $previousSlug)
                ->get()
                ->each(function (Product $product) use ($previousSlug, $dressOccasion): void {
                    $occasions = collect($product->occasions ?? [])
                        ->map(fn ($slug) => $slug === $previousSlug ? $dressOccasion->slug : $slug)
                        ->filter(fn ($slug) => is_string($slug) && trim($slug) !== '')
                        ->unique()
                        ->values()
                        ->all();

                    $product->update(['occasions' => $occasions]);
                });
        }

        return response()->json($dressOccasion->fresh());
    }

    public function destroy(DressOccasion $dressOccasion)
    {
        $inUse = Product::query()
            ->whereJsonContains('occasions', $dressOccasion->slug)
            ->exists();

        if ($inUse) {
            return response()->json([
                'message' => 'Occasion is in use by products and cannot be deleted.',
            ], 422);
        }

        $dressOccasion->delete();

        return response()->noContent();
    }

    private function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug, '_') ?: 'ocasion';
        $candidate = $base;
        $i = 2;

        while (
            DressOccasion::query()
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->where('slug', $candidate)
                ->exists()
        ) {
            $candidate = $base.'_'.$i;
            $i++;
        }

        return $candidate;
    }
}
