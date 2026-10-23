<?php

namespace App\Repositories\Eloquent;

use App\Models\Order;
use App\Models\User;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Str;

class EloquentOrderRepository implements OrderRepositoryInterface
{
    public function create(User $user, array $lines, string $total): Order
    {
        $order = $user->orders()->create([
            'number' => 'ORD-'.strtoupper(Str::random(10)),
            'status' => Order::STATUS_PENDING,
            'total' => $total,
        ]);

        $order->items()->createMany($lines);

        return $order;
    }

    public function findWithDetails(int $id): ?Order
    {
        return Order::query()
            ->with(['user:id,name,email', 'items.product:id,name,sku'])
            ->find($id);
    }

    /**
     * Eager loading + withCount: 2 consultas sin importar cuántos pedidos haya.
     */
    public function latestWithSummary(int $limit = 10): Collection
    {
        return Order::query()
            ->select(['id', 'user_id', 'number', 'status', 'total', 'invoice_path', 'created_at'])
            ->with('user:id,name')
            ->withCount('items')
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function idsWithoutInvoice(): BaseCollection
    {
        return Order::query()->whereNull('invoice_path')->pluck('id');
    }

    public function markAsInvoiced(Order $order, string $invoicePath): void
    {
        $order->update([
            'invoice_path' => $invoicePath,
            'status' => $order->status === Order::STATUS_PENDING ? Order::STATUS_INVOICED : $order->status,
        ]);
    }

    public function markAsCompleted(Order $order): void
    {
        $order->update(['status' => Order::STATUS_COMPLETED]);
    }
}
