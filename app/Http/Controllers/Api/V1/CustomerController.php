<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->string('search'));

        $customers = Customer::query()
            ->withCount('rentalBlocks')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(50);

        return response()->json($customers);
    }

    public function show(Customer $customer)
    {
        $customer->load([
            'rentalBlocks' => fn ($query) => $query
                ->with('product:id,name')
                ->orderByDesc('start_date'),
        ]);

        return response()->json($customer);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $customer = Customer::query()->create($validated);

        return response()->json($customer, 201);
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $customer->update($validated);

        if (array_key_exists('name', $validated)) {
            $customer->rentalBlocks()->update(['customer_name' => $customer->name]);
        }

        return response()->json($customer->fresh()->loadCount('rentalBlocks'));
    }

    public function destroy(Customer $customer)
    {
        if ($customer->rentalBlocks()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: la clienta tiene rentas registradas.',
            ], 422);
        }

        $customer->delete();

        return response()->noContent();
    }
}
