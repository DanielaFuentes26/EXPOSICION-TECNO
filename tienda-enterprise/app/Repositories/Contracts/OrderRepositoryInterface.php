<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

interface OrderRepositoryInterface
{
    /**
     * @param  array<int, array{product_id: int, quantity: int, unit_price: string, subtotal: string}>  $lines
     */
    public function create(User $user, array $lines, string $total): Order;

    public function findWithDetails(int $id): ?Order;

    /**
     * @return Collection<int, Order>
     */
    public function latestWithSummary(int $limit = 10): Collection;

    /**
     * @return BaseCollection<int, int>
     */
    public function idsWithoutInvoice(): BaseCollection;

    public function markAsInvoiced(Order $order, string $invoicePath): void;

    public function markAsCompleted(Order $order): void;
}
