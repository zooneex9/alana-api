<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Support\DressTaxonomy;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->string('category')->isNotEmpty(), fn ($query) => $query->where('category', $request->string('category')->toString()))
            ->when($request->string('dress_length')->isNotEmpty(), fn ($query) => $query->where('dress_length', $request->string('dress_length')->toString()))
            ->when($request->string('occasions')->isNotEmpty(), function ($query) use ($request): void {
                $slugs = array_values(array_filter(array_map(
                    'trim',
                    explode(',', $request->string('occasions')->toString())
                )));
                $allowed = array_values(array_intersect($slugs, DressTaxonomy::OCCASIONS));
                if ($allowed === []) {
                    return;
                }
                $query->where(function ($inner) use ($allowed): void {
                    foreach ($allowed as $slug) {
                        $inner->orWhereJsonContains('occasions', $slug);
                    }
                });
            })
            ->when($request->boolean('is_vintage') || $request->string('vintage')->toString() === '1', fn ($query) => $query->where('is_vintage', true))
            ->when($request->boolean('is_new_arrival') || $request->string('new')->toString() === '1', fn ($query) => $query->where('is_new_arrival', true))
            ->when($request->boolean('is_dr_fave') || $request->string('faves')->toString() === '1', fn ($query) => $query->where('is_dr_fave', true))
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
        $payload = Arr::except($request->validated(), ['images', 'image_urls']);

        try {
            $images = $this->collectUploadedImages($request);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Image upload failed. Verify S3 credentials, bucket, region, and PutObject permissions.',
            ], 422);
        }
        $images = array_merge($images, $this->collectImageUrlsFromRequest($request));

        $payload['images'] = $images;

        try {
            $product = Product::query()->create($payload);
        } catch (Throwable) {
            foreach ($images as $img) {
                if (! empty($img['path'])) {
                    Storage::disk('s3')->delete($img['path']);
                }
            }
            throw new \RuntimeException('Product create failed.');
        }

        return response()->json($product, 201);
    }

    public function show(Product $product)
    {
        return response()->json($product);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $payload = Arr::except($request->validated(), [
            'images',
            'remove_image_paths',
            'remove_image_urls',
            'image_urls',
        ]);

        $current = $product->imagesList();
        $pathsToDelete = [];

        $removePaths = $this->decodeJsonList($request->input('remove_image_paths'));
        $removeUrls = $this->decodeJsonList($request->input('remove_image_urls'));

        $filtered = [];
        foreach ($current as $img) {
            if (! is_array($img)) {
                continue;
            }
            $p = $img['path'] ?? null;
            $u = $img['url'] ?? null;
            if ($p && in_array($p, $removePaths, true)) {
                $pathsToDelete[] = $p;

                continue;
            }
            if ((! $p || $p === '') && $u && in_array($u, $removeUrls, true)) {
                continue;
            }
            $filtered[] = $img;
        }

        foreach (array_unique($pathsToDelete) as $path) {
            if ($path) {
                Storage::disk('s3')->delete($path);
            }
        }

        $merged = $filtered;
        try {
            $newUploads = $this->collectUploadedImages($request);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Image upload failed. Verify S3 credentials, bucket, region, and PutObject permissions.',
            ], 422);
        }
        foreach ($newUploads as $row) {
            $merged[] = $row;
        }
        foreach ($this->collectImageUrlsFromRequest($request) as $row) {
            $merged[] = $row;
        }

        try {
            $product->update(array_merge($payload, ['images' => $merged]));
        } catch (Throwable) {
            foreach ($newUploads as $img) {
                if (! empty($img['path'])) {
                    Storage::disk('s3')->delete($img['path']);
                }
            }
            throw new \RuntimeException('Product update failed.');
        }

        return response()->json($product->fresh());
    }

    public function destroy(Product $product)
    {
        foreach ($product->imagesList() as $img) {
            if (is_array($img) && ! empty($img['path'])) {
                Storage::disk('s3')->delete($img['path']);
            }
        }

        $product->delete();

        return response()->noContent();
    }

    public function updateStatus(Request $request, Product $product)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:available,reserved,rented'],
        ]);

        $product->update(['status' => $validated['status']]);

        return response()->json($product->fresh());
    }

    /**
     * @return array<int, array{path: ?string, url: string}>
     */
    private function collectUploadedImages(Request $request): array
    {
        if (! $request->hasFile('images')) {
            return [];
        }

        $out = [];
        foreach ($request->file('images', []) as $file) {
            if ($file instanceof UploadedFile && $file->isValid()) {
                try {
                    $out[] = $this->uploadImageToS3($file);
                } catch (Throwable $e) {
                    foreach ($out as $img) {
                        if (! empty($img['path'])) {
                            Storage::disk('s3')->delete($img['path']);
                        }
                    }

                    throw $e;
                }
            }
        }

        return $out;
    }

    /**
     * @return array<int, array{path: null, url: string}>
     */
    private function collectImageUrlsFromRequest(Request $request): array
    {
        $raw = $request->input('image_urls');
        if ($raw === null || $raw === '') {
            return [];
        }
        $urls = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($urls)) {
            return [];
        }
        $out = [];
        foreach ($urls as $url) {
            if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                $out[] = ['path' => null, 'url' => $url];
            }
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    private function decodeJsonList(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (is_array($raw)) {
            $list = $raw;
        } else {
            $list = json_decode((string) $raw, true);
        }
        if (! is_array($list)) {
            return [];
        }
        $out = [];
        foreach ($list as $v) {
            if (is_string($v) && $v !== '') {
                $out[] = $v;
            }
        }

        return $out;
    }

    /**
     * @return array{path: string, url: string}
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
            'path' => $path,
            'url' => Storage::disk('s3')->url($path),
        ];
    }
}
