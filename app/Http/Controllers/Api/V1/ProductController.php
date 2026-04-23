<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->string('category')->isNotEmpty(), fn ($query) => $query->where('category', $request->string('category')->toString()))
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request): void {
                $term = $request->string('search')->toString();
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%");
                });
            })
            ->latest('date_added')
            ->paginate(24);

        return response()->json($products);
    }

    public function store(StoreProductRequest $request)
    {
        $payload = $request->validated();

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 's3');
            $payload['image_path'] = $path;
            $payload['image_url'] = Storage::disk('s3')->url($path);
        }

        $product = Product::query()->create($payload);

        return response()->json($product, 201);
    }

    public function show(Product $product)
    {
        return response()->json($product);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $payload = $request->validated();

        if ($request->hasFile('image')) {
            if ($product->image_path) {
                Storage::disk('s3')->delete($product->image_path);
            }

            $path = $request->file('image')->store('products', 's3');
            $payload['image_path'] = $path;
            $payload['image_url'] = Storage::disk('s3')->url($path);
        }

        $product->update($payload);

        return response()->json($product->fresh());
    }

    public function destroy(Product $product)
    {
        if ($product->image_path) {
            Storage::disk('s3')->delete($product->image_path);
        }

        $product->delete();

        return response()->noContent();
    }

    public function updateStatus(Request $request, Product $product)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:available,separated,sold'],
        ]);

        $product->update(['status' => $validated['status']]);

        return response()->json($product->fresh());
    }
}
