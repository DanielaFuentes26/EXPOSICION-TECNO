<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Listener EN COLA (ShouldQueue) y de baja prioridad: avisa si algún
 * producto del pedido quedó con poco stock.
 *
 * Agregar más reacciones al pedido (Slack, analytics, puntos de fidelidad...)
 * es crear otro listener como este, sin tocar OrderService.
 */
class CheckLowStock implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'low';

    public function __construct(private ProductRepositoryInterface $products) {}

    public function handle(OrderPlaced $event): void
    {
        $productIds = $event->order->items()->pluck('product_id')->all();

        foreach ($this->products->lowStockAmong($productIds) as $product) {
            Log::warning('Stock bajo', [
                'product_id' => $product->id,
                'sku' => $product->sku,
                'stock' => $product->stock,
            ]);
        }
    }
}
