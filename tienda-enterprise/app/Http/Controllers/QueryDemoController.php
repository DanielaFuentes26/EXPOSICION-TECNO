<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Demo en vivo del problema N+1: mismo resultado, distinta cantidad de consultas.
 */
class QueryDemoController extends Controller
{
    private const int ORDERS = 20;

    /**
     * ❌ Lazy loading: 1 consulta de pedidos + 1 por usuario + 1 por items = ~41 consultas.
     */
    public function lazyLoading(): JsonResponse
    {
        return $this->measure('lazy loading (N+1)', function (): array {
            return Order::query()->latest()->limit(self::ORDERS)->get()
                ->map(fn (Order $order): array => [
                    'number' => $order->number,
                    'customer' => $order->user->name,
                    'items' => $order->items->count(),
                ])
                ->all();
        });
    }

    /**
     * ✅ Eager loading + withCount + select: 2 consultas.
     */
    public function eagerLoading(): JsonResponse
    {
        return $this->measure('eager loading', function (): array {
            return Order::query()
                ->select(['id', 'user_id', 'number', 'created_at'])
                ->with('user:id,name')
                ->withCount('items')
                ->latest()
                ->limit(self::ORDERS)
                ->get()
                ->map(fn (Order $order): array => [
                    'number' => $order->number,
                    'customer' => $order->user->name,
                    'items' => $order->items_count,
                ])
                ->all();
        });
    }

    /**
     * @param  Closure(): array<int, array<string, mixed>>  $callback
     */
    private function measure(string $strategy, Closure $callback): JsonResponse
    {
        DB::connection()->getPdo();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $startedAt = hrtime(true);

        $rows = $callback();

        $elapsedMs = round((hrtime(true) - $startedAt) / 1e6, 1);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        return response()->json([
            'strategy' => $strategy,
            'queries' => count($queries),
            'time_ms' => $elapsedMs,
            'first_queries' => array_slice(array_column($queries, 'query'), 0, 5),
            'sample' => array_slice($rows, 0, 3),
        ], options: JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
