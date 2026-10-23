<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Jobs\GenerateInvoice;
use App\Jobs\SendOrderConfirmationEmail;
use Illuminate\Support\Facades\Bus;

/**
 * Listener síncrono y barato: solo encola una CADENA de jobs.
 *
 * La cadena garantiza el orden: el correo se envía únicamente si la
 * factura se generó bien, porque la lleva adjunta.
 */
class ProcessPlacedOrder
{
    public function handle(OrderPlaced $event): void
    {
        Bus::chain([
            new GenerateInvoice($event->order),
            new SendOrderConfirmationEmail($event->order),
        ])->onQueue('default')->dispatch();
    }
}
