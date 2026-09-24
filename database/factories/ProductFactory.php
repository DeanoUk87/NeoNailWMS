<?php

namespace Database\Factories;

use App\Models\FulfilmentClient;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'fulfilment_client_id' => FulfilmentClient::factory(),
            'internal_sku'         => strtoupper(fake()->unique()->bothify('??-####')),
            'name'                 => fake()->words(3, true),
            'scan_code'            => null,
            'manufacturer_barcode' => null,
            'unit_of_measure'      => fake()->randomElement(['each', 'pack', 'case']),
            'pack_qty'             => null,
            'pack_qty_confirmed'   => false,
            'is_active'            => true,
        ];
    }

    public function withEan(): static
    {
        return $this->state(fn () => [
            'manufacturer_barcode' => fake()->ean13(),
        ]);
    }

    public function withScanCode(): static
    {
        return $this->state(fn () => [
            'scan_code' => strtoupper(fake()->bothify('INT-########')),
        ]);
    }

    public function withUnverifiedPack(int $qty = 10): static
    {
        return $this->state(fn () => [
            'unit_of_measure'    => 'pack',
            'pack_qty'           => $qty,
            'pack_qty_confirmed' => false,
        ]);
    }

    public function withVerifiedPack(int $qty = 10): static
    {
        return $this->state(fn () => [
            'unit_of_measure'    => 'pack',
            'pack_qty'           => $qty,
            'pack_qty_confirmed' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
