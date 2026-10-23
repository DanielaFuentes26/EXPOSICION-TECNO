<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Controlador delgado: valida (FormRequest), delega (Service) y responde.
 * No sabe nada de facturas, correos ni colas.
 */
class OrderController extends Controller
{
    public function store(StoreOrderRequest $request, OrderService $orderService): RedirectResponse
    {
        $startedAt = hrtime(true);

        $order = $orderService->placeOrder(
            User::findOrFail($request->integer('user_id')),
            $request->items(),
        );

        $elapsedMs = round((hrtime(true) - $startedAt) / 1e6);

        return redirect()
            ->route('dashboard')
            ->with('status', "Pedido {$order->number} creado en {$elapsedMs} ms. La factura y el correo se procesan en la cola.");
    }

    public function invoice(Order $order): Response
    {
        abort_if($order->invoice_path === null || ! Storage::disk('local')->exists($order->invoice_path), 404);

        return response(Storage::disk('local')->get($order->invoice_path))
            ->header('Content-Type', 'text/html; charset=UTF-8');
    }
}
