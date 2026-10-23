<?php

namespace App\Console\Commands;

use App\Jobs\GenerateInvoice;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Bus\Batch;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bus::batch: lanza muchos jobs en paralelo y permite seguir su progreso
 * (tabla job_batches) y reaccionar cuando terminan o fallan.
 */
#[Signature('tienda:generar-facturas {--limit=100 : Máximo de pedidos a procesar}')]
#[Description('Genera en lote las facturas de los pedidos que aún no tienen')]
class RegenerateInvoices extends Command
{
    public function handle(OrderRepositoryInterface $orders): int
    {
        $orderIds = $orders->idsWithoutInvoice()->take((int) $this->option('limit'));

        if ($orderIds->isEmpty()) {
            $this->components->info('Todos los pedidos ya tienen factura.');

            return self::SUCCESS;
        }

        $jobs = Order::query()
            ->whereIn('id', $orderIds)
            ->get(['id'])
            ->map(fn (Order $order): GenerateInvoice => new GenerateInvoice($order))
            ->all();

        $batch = Bus::batch($jobs)
            ->name('Generar facturas pendientes')
            ->onQueue('low')
            ->allowFailures()
            ->then(fn (Batch $batch) => Log::info("Lote {$batch->id} terminado: {$batch->processedJobs()} facturas."))
            ->catch(fn (Batch $batch, Throwable $exception) => Log::error("Falló un job del lote {$batch->id}", ['exception' => $exception]))
            ->dispatch();

        $this->components->info("Lote {$batch->id} encolado con {$batch->totalJobs} jobs en la cola 'low'.");
        $this->line('  Procésalo con: php artisan queue:work --queue=default,low');

        return self::SUCCESS;
    }
}
