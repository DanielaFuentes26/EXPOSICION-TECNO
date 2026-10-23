<?php

namespace App\Jobs;

use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Mail;

/**
 * Segundo eslabón de la cadena: envía el correo con la factura adjunta.
 * Un servidor SMTP caído es un fallo transitorio, por eso hay reintentos.
 */
#[Tries(5)]
#[Backoff(30)]
class SendOrderConfirmationEmail implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function handle(OrderRepositoryInterface $orders): void
    {
        $order = $orders->findWithDetails($this->order->id);

        if ($order === null || $order->status === Order::STATUS_COMPLETED) {
            return;
        }

        Mail::to($order->user)->send(new OrderConfirmation($order));

        $orders->markAsCompleted($order);
    }
}
