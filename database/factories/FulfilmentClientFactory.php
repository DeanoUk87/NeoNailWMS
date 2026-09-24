<?php

namespace Database\Factories;

use App\Models\FulfilmentClient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FulfilmentClient>
 */
class FulfilmentClientFactory extends Factory
{
    protected $model = FulfilmentClient::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'is_active' => true,
        ];
    }
}
