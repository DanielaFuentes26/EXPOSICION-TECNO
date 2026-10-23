<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Compara dos formas de calcular ventas por cliente sobre muchos registros:
 *  1) lazyById(): recorre en bloques de 500 sin cargar todo en memoria.
 *  2) GROUP BY: deja que la base de datos haga la agregación (lo ideal).
 */
#[Signature('tienda:reporte-ventas')]
#[Description('Reporte de ventas por cliente: lazyById vs agregación en SQL')]
class SalesReport extends Command
{
    public function handle(): int
    {
        $startedAt = hrtime(true);
        $memoryBefore = memory_get_usage();
        $totalsByUser = [];

        foreach (Order::query()->select(['id', 'user_id', 'total'])->lazyById(500) as $order) {
            $totalsByUser[$order->user_id] = bcadd($totalsByUser[$order->user_id] ?? '0', (string) $order->total, 2);
        }

        $this->components->twoColumnDetail(
            'lazyById (PHP)',
            sprintf('%d clientes · %.1f ms · %.2f MB', count($totalsByUser), (hrtime(true) - $startedAt) / 1e6, (memory_get_usage() - $memoryBefore) / 1048576),
        );

        $startedAt = hrtime(true);

        $topCustomers = Order::query()
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->groupBy('users.id', 'users.name')
            ->selectRaw('users.name as customer, COUNT(orders.id) as orders, SUM(orders.total) as total')
            ->orderByDesc('total')
            ->limit(10)
            ->toBase()
            ->get();

        $this->components->twoColumnDetail(
            'GROUP BY (SQL)',
            sprintf('1 consulta · %.1f ms', (hrtime(true) - $startedAt) / 1e6),
        );

        $this->newLine();
        $this->table(
            ['Cliente', 'Pedidos', 'Total'],
            $topCustomers->map(fn (object $row): array => [$row->customer, $row->orders, number_format((float) $row->total, 2)])->all(),
        );

        return self::SUCCESS;
    }
}
