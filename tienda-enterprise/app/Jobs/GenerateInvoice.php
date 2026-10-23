<?php

namespace App\Jobs;

use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Tarea pesada: generar la factura del pedido.
 *
 * - ShouldQueue: no se ejecuta en la petición HTTP, va a la cola.
 * - Tries/Backoff: si falla, se reintenta 3 veces esperando 10s, 30s, 60s.
 * - Timeout: menor que el retry_after de config/queue.php (90s).
 * - Batchable: también se puede lanzar en lotes (ver RegenerateInvoices).
 * - Idempotente: si la factura ya existe, no la vuelve a crear.
 */
#[Tries(3)]
#[Backoff(10, 30, 60)]
#[Timeout(60)]
class GenerateInvoice implements ShouldQueue
{
    use Batchable, Queueable;

    public function __construct(public Order $order) {}

    public function handle(OrderRepositoryInterface $orders): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $order = $orders->findWithDetails($this->order->id);

        if ($order === null) {
            return;
        }

        if ($order->invoice_path !== null && Storage::disk('local')->exists($order->invoice_path)) {
            return;
        }

        // Simula un proceso lento (generar PDF, llamar a un servicio de facturación...).
        sleep((int) config('tienda.invoice_delay_seconds'));

        $path = "invoices/{$order->number}.html";

        Storage::disk('local')->put($path, view('invoices.invoice', ['order' => $order])->render());

        $orders->markAsInvoiced($order, $path);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('No se pudo generar la factura', [
            'order_id' => $this->order->id,
            'exception' => $exception,
        ]);
    }
}
