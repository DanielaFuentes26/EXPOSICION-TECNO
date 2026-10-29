<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => fake()->randomElement(['Laptop', 'Mouse inalámbrico',
             'Teclado mecánico', 'Monitor 24 pulgadas', 'Audífonos', 'Cámara web', 
             'Disco duro externo', 'Memoria USB', 'Impresora', 'Parlante bluetooth', 
             'Silla de oficina', 'Escritorio']),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####-??')),
            'price' => fake()->randomFloat(2, 5, 500),
            'stock' => fake()->numberBetween(10, 100),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['active' => false]);
    }

    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => ['stock' => 2]);
    }
}
