<?php

namespace Tests\Feature;

use App\Events\OrderPlaced;
use App\Jobs\GenerateInvoice;
use App\Jobs\SendOrderConfirmationEmail;
use App\Listeners\CheckLowStock;
use App\Listeners\ProcessPlacedOrder;
use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Eloquent\EloquentOrderRepository;
use App\Repositories\Eloquent\EloquentProductRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_repository_interfaces_resolve_to_eloquent_implementations(): void
    {
        $this->assertInstanceOf(EloquentProductRepository::class, app(ProductRepositoryInterface::class));
        $this->assertInstanceOf(EloquentOrderRepository::class, app(OrderRepositoryInterface::class));
    }

    public function test_dashboard_renders(): void
    {
        Order::factory()->create();

        $this->get(route('dashboard'))->assertOk()->assertSee('Tienda Enterprise');
    }

    public function test_placing_an_order_runs_the_full_flow(): void
    {
        Storage::fake('local');
        Mail::fake();

        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => '10.50', 'stock' => 20]);

        $this->post(route('orders.store'), [
            'user_id' => $user->id,
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
        ])->assertRedirect(route('dashboard'))->assertSessionHas('status');

        $order = Order::sole();

        $this->assertSame('31.50', $order->total);
        $this->assertSame(17, $product->fresh()->stock);
        $this->assertSame(Order::STATUS_COMPLETED, $order->status);
        Storage::disk('local')->assertExists($order->invoice_path);
        Mail::assertSent(OrderConfirmation::class, fn (OrderConfirmation $mail): bool => $mail->hasTo($user->email));
    }

    public function test_order_placed_event_queues_invoice_then_email_chain(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->post(route('orders.store'), [
            'user_id' => $user->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        Bus::assertChained([GenerateInvoice::class, SendOrderConfirmationEmail::class]);
    }

    public function test_order_placed_has_expected_listeners(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->post(route('orders.store'), [
            'user_id' => $user->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        Event::assertDispatched(OrderPlaced::class);
        Event::assertListening(OrderPlaced::class, ProcessPlacedOrder::class);
        Event::assertListening(OrderPlaced::class, CheckLowStock::class);
    }

    public function test_insufficient_stock_rolls_back_and_dispatches_nothing(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $available = Product::factory()->create(['stock' => 10]);
        $scarce = Product::factory()->create(['stock' => 1]);

        $this->post(route('orders.store'), [
            'user_id' => $user->id,
            'items' => [
                ['product_id' => $available->id, 'quantity' => 2],
                ['product_id' => $scarce->id, 'quantity' => 5],
            ],
        ])->assertSessionHasErrors('items.1.quantity');

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(10, $available->fresh()->stock);
        Event::assertNotDispatched(OrderPlaced::class);
    }

    public function test_inactive_products_cannot_be_ordered(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->inactive()->create();

        $this->post(route('orders.store'), [
            'user_id' => $user->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('items.0.product_id');
    }

    public function test_generate_invoice_is_idempotent(): void
    {
        Storage::fake('local');

        $order = Order::factory()->create();

        GenerateInvoice::dispatchSync($order);
        $path = $order->fresh()->invoice_path;
        Storage::disk('local')->put($path, 'original');

        GenerateInvoice::dispatchSync($order);

        $this->assertSame('original', Storage::disk('local')->get($path));
        $this->assertSame(Order::STATUS_INVOICED, $order->fresh()->status);
    }

    public function test_regenerate_invoices_command_dispatches_a_batch(): void
    {
        Bus::fake();

        Order::factory()->count(3)->create();

        $this->artisan('tienda:generar-facturas')->assertSuccessful();

        Bus::assertBatched(fn ($batch): bool => $batch->jobs->count() === 3 && $batch->queue() === 'low');
    }
}
