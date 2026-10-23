<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tienda Enterprise · Laravel</title>
    <style>
        :root { --bg:#f6f7f9; --card:#fff; --text:#1d2433; --muted:#667085; --line:#e4e7ec; --accent:#e5482f; --ok:#12805c; --warn:#b54708; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background:var(--bg); color:var(--text); }
        main { max-width:1100px; margin:0 auto; padding:24px 16px 48px; }
        h1 { margin:0 0 4px; font-size:26px; }
        h2 { font-size:16px; margin:0 0 12px; }
        .muted { color:var(--muted); font-size:14px; }
        .grid { display:grid; gap:16px; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); margin:20px 0; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:10px; padding:16px; }
        .stat { font-size:28px; font-weight:700; }
        .two { display:grid; gap:16px; grid-template-columns: 1fr 1fr; }
        @media (max-width:760px) { .two { grid-template-columns:1fr; } }
        label { display:block; font-size:13px; color:var(--muted); margin:10px 0 4px; }
        select, input { width:100%; padding:8px; border:1px solid var(--line); border-radius:6px; font:inherit; }
        button { margin-top:14px; background:var(--accent); color:#fff; border:0; padding:10px 16px; border-radius:6px; font-weight:600; cursor:pointer; }
        .alert { padding:12px 14px; border-radius:8px; margin-top:16px; }
        .alert.ok { background:#ecfdf3; color:var(--ok); }
        .alert.err { background:#fef3f2; color:#b42318; }
        table { width:100%; border-collapse:collapse; font-size:14px; }
        th, td { text-align:left; padding:8px; border-bottom:1px solid var(--line); }
        .table-wrap { overflow-x:auto; }
        .badge { font-size:12px; padding:2px 8px; border-radius:99px; background:#eef2f6; }
        .badge.pending { background:#fffaeb; color:var(--warn); }
        .badge.completed { background:#ecfdf3; color:var(--ok); }
        a { color:var(--accent); }
        ol { padding-left:20px; margin:0; line-height:1.7; font-size:14px; }
        code { background:#eef2f6; padding:1px 5px; border-radius:4px; font-size:13px; }
    </style>
</head>
<body>
<main>
    <h1>Tienda Enterprise</h1>
    <p class="muted">Repository · Jobs · Queues · Eventos · Optimización de consultas — base de datos: <strong>{{ config('database.default') }}</strong></p>

    @if (session('status'))
        <div class="alert ok">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert err">{{ $errors->first() }}</div>
    @endif

    <div class="grid">
        <div class="card"><div class="muted">Productos activos</div><div class="stat">{{ $stats['products'] }}</div></div>
        <div class="card"><div class="muted">Con stock bajo</div><div class="stat">{{ $stats['low_stock'] }}</div></div>
        <div class="card"><div class="muted">Valor del inventario</div><div class="stat">${{ number_format($stats['inventory_value'], 2) }}</div></div>
        <div class="card"><div class="muted">Cola default / low / fallidos</div><div class="stat">{{ $queue['default'] }} / {{ $queue['low'] }} / {{ $queue['failed'] }}</div></div>
    </div>

    <div class="two">
        <form class="card" method="POST" action="{{ route('orders.store') }}">
            @csrf
            <h2>1. Hacer un pedido</h2>
            <label for="user_id">Cliente</label>
            <select id="user_id" name="user_id">
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
            <label for="product">Producto</label>
            <select id="product" name="items[0][product_id]">
                @foreach ($products as $product)
                    <option value="{{ $product->id }}">{{ $product->name }} — ${{ $product->price }} (stock {{ $product->stock }})</option>
                @endforeach
            </select>
            <label for="quantity">Cantidad</label>
            <input id="quantity" type="number" name="items[0][quantity]" value="1" min="1" max="10">
            <button type="submit">Comprar</button>
        </form>

        <div class="card">
            <h2>¿Qué pasa al comprar?</h2>
            <ol>
                <li><code>OrderController</code> valida con <code>StoreOrderRequest</code></li>
                <li><code>OrderService</code> descuenta stock y guarda vía <strong>repositorios</strong> (transacción)</li>
                <li>Se dispara el <strong>evento</strong> <code>OrderPlaced</code> tras el commit</li>
                <li><code>ProcessPlacedOrder</code> encola la <strong>cadena de jobs</strong>: <code>GenerateInvoice</code> → <code>SendOrderConfirmationEmail</code></li>
                <li><code>CheckLowStock</code> corre en la <strong>cola</strong> <code>low</code></li>
                <li>El usuario recibe respuesta al instante; el worker hace el resto</li>
            </ol>
            <h2 style="margin-top:16px">2. Optimización de consultas</h2>
            <p class="muted" style="margin:0">
                <a href="{{ route('demo.lazy-loading') }}" target="_blank">❌ /demo/n-mas-1</a> &nbsp;vs&nbsp;
                <a href="{{ route('demo.eager-loading') }}" target="_blank">✅ /demo/eager-loading</a>
            </p>
        </div>
    </div>

    <div class="card" style="margin-top:16px">
        <h2>Últimos pedidos <span class="muted">(eager loading + withCount = 2 consultas)</span></h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Número</th><th>Cliente</th><th>Items</th><th>Total</th><th>Estado</th><th>Factura</th><th>Fecha</th></tr></thead>
                <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td>{{ $order->number }}</td>
                        <td>{{ $order->user->name }}</td>
                        <td>{{ $order->items_count }}</td>
                        <td>${{ number_format((float) $order->total, 2) }}</td>
                        <td><span class="badge {{ $order->status }}">{{ $order->status }}</span></td>
                        <td>
                            @if ($order->invoice_path)
                                <a href="{{ route('orders.invoice', $order) }}" target="_blank">ver</a>
                            @else
                                <span class="muted">en cola…</span>
                            @endif
                        </td>
                        <td class="muted">{{ $order->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted">Sin pedidos. Corre <code>php artisan migrate:fresh --seed</code></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</main>
</body>
</html>
