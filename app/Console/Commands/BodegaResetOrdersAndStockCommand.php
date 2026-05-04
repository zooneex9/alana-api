<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BodegaResetOrdersAndStockCommand extends Command
{
    protected $signature = 'bodega:reset-orders-and-stock
                            {--force : Ejecutar sin confirmación (obligatorio en producción)}';

    protected $description = 'Elimina todas las órdenes y restaura cantidad/estado de los productos según las unidades que la API hubiera descontado.';

    /**
     * Unidades de inventario que esta orden hizo decrementar en su momento (según OrderController / StripeWebhook).
     */
    private function stockUnitsThisOrderReduced(Order $order): int
    {
        $meta = is_array($order->meta) ? $order->meta : [];
        $hasMode = array_key_exists('mode', $meta);

        if ($order->stripe_checkout_session_id !== null) {
            if (($meta['mode'] ?? 'buy') === 'separate') {
                return 0;
            }

            return $order->paid_at !== null ? 1 : 0;
        }

        if ($hasMode) {
            return 0;
        }

        return 1;
    }

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        if (app()->environment('production') && ! $force) {
            $this->error('En producción debes pasar --force después de confirmar que quieres borrar todas las órdenes.');

            return self::FAILURE;
        }

        $orderCount = Order::query()->count();
        if ($orderCount === 0) {
            $this->info('No hay órdenes. Se marcarán todos los productos como disponibles.');
        } elseif (! $force && ! $this->confirm("Se eliminarán {$orderCount} órdenes y se ajustará el stock de los productos. ¿Continuar?", false)) {
            $this->info('Operación cancelada.');

            return self::SUCCESS;
        }

        $restoreByProduct = [];
        foreach (Order::query()->cursor() as $order) {
            $pid = (int) $order->product_id;
            if ($pid < 1) {
                continue;
            }
            $n = $this->stockUnitsThisOrderReduced($order);
            if ($n > 0) {
                $restoreByProduct[$pid] = ($restoreByProduct[$pid] ?? 0) + $n;
            }
        }

        $deleted = 0;
        $updated = 0;

        DB::transaction(function () use (&$deleted, &$updated, $restoreByProduct): void {
            $deleted = Order::query()->delete();

            Product::query()->eachById(function (Product $product) use (&$updated, $restoreByProduct): void {
                $add = (int) ($restoreByProduct[$product->id] ?? 0);
                $newQty = max(0, (int) $product->quantity + $add);
                $product->update([
                    'status' => 'available',
                    'quantity' => $newQty,
                ]);
                $updated++;
            }, count: 200);
        });

        $this->info("Órdenes eliminadas: {$deleted}. Productos actualizados: {$updated}.");

        return self::SUCCESS;
    }
}
