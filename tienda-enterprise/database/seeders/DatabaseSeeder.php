<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private const int ORDERS = 1000;

    private const int INSERT_CHUNK = 500;

    /**
     * Seed the application's database.
     *
     * Los pedidos se insertan en bloques de 500 filas con insert() en lugar de
     * create() uno por uno: ~10 consultas en vez de ~4000 (importante con Neon,
     * donde cada consulta viaja por internet).
     */
    public function run(): void
    {
        $users = User::factory(29)->create()->push(User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]));

        $categories = Category::factory(8)->create();
        $products = Product::factory(60)->recycle($categories)->create();
        Product::factory(3)->lowStock()->recycle($categories)->create();
        Product::factory(5)->inactive()->recycle($categories)->create();

        $orders = [];
        $itemsByNumber = [];

        for ($i = 0; $i < self::ORDERS; $i++) {
            $number = 'ORD-'.strtoupper(Str::random(10));
            $createdAt = now()->subMinutes(random_int(60, 60 * 24 * 90));
            $total = '0.00';

            foreach ($products->random(random_int(1, 4)) as $product) {
                $quantity = random_int(1, 3);
                $subtotal = bcmul((string) $product->price, (string) $quantity, 2);
                $total = bcadd($total, $subtotal, 2);

                $itemsByNumber[$number][] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $product->price,
                    'subtotal' => $subtotal,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }

            $orders[] = [
                'user_id' => $users->random()->id,
                'number' => $number,
                'status' => Order::STATUS_COMPLETED,
                'total' => $total,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        foreach (array_chunk($orders, self::INSERT_CHUNK) as $chunk) {
            Order::insert($chunk);
        }

        $orderIds = Order::query()->pluck('id', 'number');

        $items = [];
        foreach ($itemsByNumber as $number => $lines) {
            foreach ($lines as $line) {
                $items[] = ['order_id' => $orderIds[$number]] + $line;
            }
        }

        foreach (array_chunk($items, self::INSERT_CHUNK) as $chunk) {
            OrderItem::insert($chunk);
        }
    }
}
