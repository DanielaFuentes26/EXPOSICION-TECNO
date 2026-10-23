<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * "Se realizó un pedido". Quien lo dispara no sabe quién lo escucha.
 *
 * Los listeners de app/Listeners se registran solos (event discovery)
 * por el tipo del parámetro de su método handle().
 */
class OrderPlaced implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order) {}
}
