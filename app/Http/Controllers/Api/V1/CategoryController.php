<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function index()
    {
        $this->syncCategoriesFromProducts();

        return response()->json(
            Category::query()->orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories,name'],
        ]);

        $category = Category::query()->create($validated);

        return response()->json($category, 201);
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:categories,name,'.$category->id],
        ]);

        $previousName = $category->name;
        $category->update($validated);

        Product::query()
            ->where('category', $previousName)
            ->update(['category' => $category->name]);

        return response()->json($category->fresh());
    }

    public function destroy(Category $category)
    {
        $inUse = Product::query()->where('category', $category->name)->exists();
        if ($inUse) {
            return response()->json([
                'message' => 'Category is in use by products and cannot be deleted.',
            ], 422);
        }

        $category->delete();

        return response()->noContent();
    }

    private function syncCategoriesFromProducts(): void
    {
        $productCategories = Product::query()
            ->select('category')
            ->whereNotNull('category')
            ->whereRaw("TRIM(category) <> ''")
            ->distinct()
            ->pluck('category')
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->values();

        if ($productCategories->isEmpty()) {
            return;
        }

        $existing = Category::query()
            ->pluck('name')
            ->map(fn (string $name) => mb_strtolower(trim($name)))
            ->all();

        $normalizedExisting = array_flip($existing);
        $missing = $productCategories
            ->filter(fn (string $name) => ! isset($normalizedExisting[mb_strtolower($name)]))
            ->map(fn (string $name) => [
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        if ($missing === []) {
            return;
        }

        DB::table('categories')->insert($missing);
    }
}
