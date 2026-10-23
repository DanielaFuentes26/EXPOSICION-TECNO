<?php

namespace App\Services;

use App\Events\OrderPlaced;
use App\Models\Order;
use App\Models\User;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lógica de negocio de "hacer un pedido".
 *
 * Solo hace lo crítico (validar, descontar stock, guardar) dentro de una
 * transacción. Todo lo demás (factura, correo, alertas) lo dispara un
 * evento y se ejecuta en segundo plano mediante colas.
 */
class OrderService
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private OrderRepositoryInterface $orders,
    ) {}

    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     *
     * @throws ValidationException
     */
    public function placeOrder(User $user, array $items): Order
    {
        $order = DB::transaction(function () use ($user, $items): Order {
            $products = $this->products->findActiveByIds(array_column($items, 'product_id'));

            $lines = [];
            $total = '0.00';

            foreach ($items as $index => $item) {
                $product = $products->get($item['product_id']);

                if ($product === null) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => 'El producto no existe o no está activo.',
                    ]);
                }

                if (! $this->products->decrementStock($product->id, $item['quantity'])) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => "Stock insuficiente para {$product->name}.",
                    ]);
                }

                $subtotal = bcmul((string) $product->price, (string) $item['quantity'], 2);
                $total = bcadd($total, $subtotal, 2);

                $lines[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => (string) $product->price,
                    'subtotal' => $subtotal,
                ];
            }

            $order = $this->orders->create($user, $lines, $total);

            // OrderPlaced implementa ShouldDispatchAfterCommit: si la transacción
            // falla, el evento se descarta y no se envía ningún correo fantasma.
            OrderPlaced::dispatch($order);

            return $order;
        });

        $this->products->forgetCatalogStats();

        return $order;
    }
}
