<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\RentalBlock;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RentalBlockController extends Controller
{
    public function availability(Request $request, Product $product)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : Carbon::today()->startOfDay();
        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : $from->copy()->addMonths(3)->endOfDay();

        $blocks = RentalBlock::query()
            ->where('product_id', $product->id)
            ->where('start_date', '<=', $to->toDateString())
            ->where('end_date', '>=', $from->toDateString())
            ->orderBy('start_date')
            ->get(['id', 'start_date', 'end_date']);

        return response()->json([
            'product_id' => $product->id,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'blocks' => $blocks->map(fn ($block) => [
                'id' => $block->id,
                'start_date' => $block->start_date->toDateString(),
                'end_date' => $block->end_date->toDateString(),
            ]),
        ]);
    }

    public function productHistory(Product $product)
    {
        $blocks = RentalBlock::query()
            ->where('product_id', $product->id)
            ->with(['customer:id,name,phone,email'])
            ->orderByDesc('start_date')
            ->get();

        return response()->json([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'rentals' => $blocks,
        ]);
    }

    public function index(Request $request)
    {
        $blocks = RentalBlock::query()
            ->with(['product:id,name', 'customer:id,name,phone,email'])
            ->when($request->filled('product_id'), fn ($q) => $q->where('product_id', $request->integer('product_id')))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->integer('customer_id')))
            ->when($request->filled('from'), fn ($q) => $q->where('end_date', '>=', $request->string('from')->toString()))
            ->when($request->filled('to'), fn ($q) => $q->where('start_date', '<=', $request->string('to')->toString()))
            ->orderByDesc('start_date')
            ->paginate(50);

        return response()->json($blocks);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'in:blocked,reserved'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->syncCustomerName($validated);

        $this->assertNoOverlap(
            (int) $validated['product_id'],
            $validated['start_date'],
            $validated['end_date'],
        );

        $block = RentalBlock::query()->create($validated);

        return response()->json(
            $block->load(['product:id,name', 'customer:id,name,phone,email']),
            201,
        );
    }

    public function update(Request $request, RentalBlock $rentalBlock)
    {
        $validated = $request->validate([
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'status' => ['sometimes', 'in:blocked,reserved'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->syncCustomerName($validated);

        $start = $validated['start_date'] ?? $rentalBlock->start_date->toDateString();
        $end = $validated['end_date'] ?? $rentalBlock->end_date->toDateString();

        if ($end < $start) {
            throw ValidationException::withMessages([
                'end_date' => 'La fecha fin debe ser posterior o igual a la fecha inicio.',
            ]);
        }

        $this->assertNoOverlap(
            $rentalBlock->product_id,
            $start,
            $end,
            $rentalBlock->id,
        );

        $rentalBlock->update($validated);

        return response()->json(
            $rentalBlock->fresh()->load(['product:id,name', 'customer:id,name,phone,email']),
        );
    }

    public function destroy(RentalBlock $rentalBlock)
    {
        $rentalBlock->delete();

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncCustomerName(array &$validated): void
    {
        if (empty($validated['customer_id'])) {
            return;
        }

        $customer = Customer::query()->find($validated['customer_id']);
        if ($customer) {
            $validated['customer_name'] = $customer->name;
        }
    }

    private function assertNoOverlap(int $productId, string $start, string $end, ?int $ignoreId = null): void
    {
        $query = RentalBlock::query()
            ->where('product_id', $productId)
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'start_date' => 'Las fechas se solapan con un bloqueo o reserva existente.',
            ]);
        }
    }
}
