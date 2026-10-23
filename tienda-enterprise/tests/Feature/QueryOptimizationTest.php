<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueryOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $product = Product::factory()->create();

        Order::factory()->count(20)->create()->each(function (Order $order) use ($product): void {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => $product->price,
                'subtotal' => $product->price,
            ]);
        });
    }

    public function test_lazy_loading_demo_runs_one_query_per_relation_per_order(): void
    {
        $this->getJson(route('demo.lazy-loading'))
            ->assertOk()
            ->assertJsonPath('queries', 1 + 20 + 20);
    }

    public function test_eager_loading_demo_runs_two_queries(): void
    {
        $this->getJson(route('demo.eager-loading'))
            ->assertOk()
            ->assertJsonPath('queries', 2);
    }

    public function test_sales_report_command_runs(): void
    {
        $this->artisan('tienda:reporte-ventas')->assertSuccessful();
    }
}
