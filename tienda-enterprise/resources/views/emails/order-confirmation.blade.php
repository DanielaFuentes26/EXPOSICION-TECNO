<p>Hola {{ $order->user->name }},</p>

<p>Tu pedido <strong>{{ $order->number }}</strong> por <strong>${{ number_format((float) $order->total, 2) }}</strong> fue confirmado.</p>

<ul>
@foreach ($order->items as $item)
    <li>{{ $item->quantity }} × {{ $item->product->name }}</li>
@endforeach
</ul>

<p>Adjuntamos tu factura. ¡Gracias por tu compra!</p>
