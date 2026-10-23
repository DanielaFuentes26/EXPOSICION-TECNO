<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

/**
 * Contrato del repositorio de productos.
 *
 * El resto de la aplicación depende de esta interfaz, nunca de Eloquent
 * directamente. Si mañana los productos vienen de una API externa, solo
 * se crea otra implementación y se cambia el binding en RepositoryServiceProvider.
 */
interface ProductRepositoryInterface
{
    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, Product>
     */
    public function findActiveByIds(array $ids): Collection;

    /**
     * @return Collection<int, Product>
     */
    public function activeForSelect(int $limit = 30): Collection;

    /**
     * Descuenta stock de forma atómica. Devuelve false si no había suficiente.
     */
    public function decrementStock(int $productId, int $quantity): bool;

    /**
     * @param  array<int, int>  $ids
     * @return Collection<int, Product>
     */
    public function lowStockAmong(array $ids): Collection;

    /**
     * @return array{products: int, low_stock: int, inventory_value: float}
     */
    public function catalogStats(): array;

    public function forgetCatalogStats(): void;
}
