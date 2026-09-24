<?php

namespace Database\Factories;

use App\Models\FulfilmentClient;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'fulfilment_client_id' => FulfilmentClient::factory(),
            'name' => fake()->city().' Warehouse',
        ];
    }
}
