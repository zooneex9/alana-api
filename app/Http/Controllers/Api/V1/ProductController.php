<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Throwable;

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
            try {
                $payload = [
                    ...$payload,
                    ...$this->uploadImageToS3($request->file('image')),
                ];
            } catch (Throwable) {
                return response()->json([
                    'message' => 'Image upload failed. Verify S3 credentials, bucket, region, and PutObject permissions.',
                ], 422);
            }
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
            try {
                $uploadedImage = $this->uploadImageToS3($request->file('image'));
            } catch (Throwable) {
                return response()->json([
                    'message' => 'Image upload failed. Verify S3 credentials, bucket, region, and PutObject permissions.',
                ], 422);
            }

            // Only delete previous object after the new upload succeeded.
            if ($product->image_path) {
                Storage::disk('s3')->delete($product->image_path);
            }

            $payload = [
                ...$payload,
                ...$uploadedImage,
            ];
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

    /**
     * @return array{image_path: string, image_url: string}
     */
    private function uploadImageToS3(UploadedFile $image): array
    {
        try {
            $path = $image->store('products', 's3');
        } catch (Throwable $exception) {
            Log::error('S3 upload threw an exception.', [
                'disk' => 's3',
                'bucket' => config('filesystems.disks.s3.bucket'),
                'region' => config('filesystems.disks.s3.region'),
                'endpoint' => config('filesystems.disks.s3.endpoint'),
                'aws_url' => config('filesystems.disks.s3.url'),
                'original_name' => $image->getClientOriginalName(),
                'mime_type' => $image->getClientMimeType(),
                'size' => $image->getSize(),
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        if (! is_string($path) || $path === '') {
            Log::error('S3 upload returned empty path.', [
                'disk' => 's3',
                'bucket' => config('filesystems.disks.s3.bucket'),
                'region' => config('filesystems.disks.s3.region'),
                'endpoint' => config('filesystems.disks.s3.endpoint'),
                'aws_url' => config('filesystems.disks.s3.url'),
                'original_name' => $image->getClientOriginalName(),
                'mime_type' => $image->getClientMimeType(),
                'size' => $image->getSize(),
            ]);

            throw new \RuntimeException('S3 upload returned an invalid path.');
        }

        return [
            'image_path' => $path,
            'image_url' => Storage::disk('s3')->url($path),
        ];
    }
}
