<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Factura {{ $order->number }}</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 700px; margin: 40px auto; padding: 0 16px; color: #1d2433; background: #fff; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #e4e7ec; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <h1>Factura {{ $order->number }}</h1>
    <p>Cliente: <strong>{{ $order->user->name }}</strong> ({{ $order->user->email }})<br>
       Fecha: {{ $order->created_at->format('d/m/Y H:i') }}</p>

    <table>
        <thead><tr><th>Producto</th><th>SKU</th><th class="right">Cant.</th><th class="right">Precio</th><th class="right">Subtotal</th></tr></thead>
        <tbody>
        @foreach ($order->items as $item)
            <tr>
                <td>{{ $item->product->name }}</td>
                <td>{{ $item->product->sku }}</td>
                <td class="right">{{ $item->quantity }}</td>
                <td class="right">${{ number_format((float) $item->unit_price, 2) }}</td>
                <td class="right">${{ number_format((float) $item->subtotal, 2) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr><th colspan="4" class="right">Total</th><th class="right">${{ number_format((float) $order->total, 2) }}</th></tr></tfoot>
    </table>
</body>
</html>
