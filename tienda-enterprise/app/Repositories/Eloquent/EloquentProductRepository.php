<?php

namespace App\Repositories\Eloquent;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class EloquentProductRepository implements ProductRepositoryInterface
{
    private const string STATS_CACHE_KEY = 'catalog.stats';

    public function findActiveByIds(array $ids): Collection
    {
        return Product::query()
            ->active()
            ->select(['id', 'name', 'price', 'stock'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    public function activeForSelect(int $limit = 30): Collection
    {
        return Product::query()
            ->active()
            ->select(['id', 'name', 'price', 'stock'])
            ->where('stock', '>', 0)
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * Un solo UPDATE condicional: evita la condición de carrera de
     * "leer stock → validar → guardar" cuando dos clientes compran a la vez.
     */
    public function decrementStock(int $productId, int $quantity): bool
    {
        return Product::query()
            ->whereKey($productId)
            ->where('stock', '>=', $quantity)
            ->decrement('stock', $quantity) === 1;
    }

    public function lowStockAmong(array $ids): Collection
    {
        return Product::query()
            ->select(['id', 'name', 'sku', 'stock'])
            ->whereIn('id', $ids)
            ->where('stock', '<', Product::LOW_STOCK_THRESHOLD)
            ->get();
    }

    /**
     * Estadísticas pesadas cacheadas 10 minutos. La agregación la hace
     * la base de datos (COUNT / SUM), no PHP.
     */
    public function catalogStats(): array
    {
        return Cache::remember(self::STATS_CACHE_KEY, now()->addMinutes(10), function (): array {
            $stats = Product::query()
                ->active()
                ->toBase()
                ->selectRaw('COUNT(*) as products')
                ->selectRaw('SUM(CASE WHEN stock < ? THEN 1 ELSE 0 END) as low_stock', [Product::LOW_STOCK_THRESHOLD])
                ->selectRaw('COALESCE(SUM(price * stock), 0) as inventory_value')
                ->first();

            return [
                'products' => (int) $stats->products,
                'low_stock' => (int) $stats->low_stock,
                'inventory_value' => (float) $stats->inventory_value,
            ];
        });
    }

    public function forgetCatalogStats(): void
    {
        Cache::forget(self::STATS_CACHE_KEY);
    }
}
