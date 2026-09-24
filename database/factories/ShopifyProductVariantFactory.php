<?php

namespace Database\Factories;

use App\Models\FulfilmentClient;
use App\Models\ShopifyProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopifyProductVariant>
 */
class ShopifyProductVariantFactory extends Factory
{
    protected $model = ShopifyProductVariant::class;

    public function definition(): array
    {
        return [
            'fulfilment_client_id' => FulfilmentClient::factory(),
            'shop_domain'          => fake()->domainName(),
            'variant_gid'          => 'gid://shopify/ProductVariant/' . fake()->unique()->numerify('##########'),
            'product_id'           => null,
            'source_sku'           => null,
            'source_barcode'       => null,
            'shopify_title'        => fake()->words(2, true),
            'mapping_status'       => 'unmapped',
            'provenance'           => 'csv_import',
            'exception_reason'     => null,
        ];
    }

    public function mapped(int $productId): static
    {
        return $this->state(fn () => [
            'product_id'     => $productId,
            'mapping_status' => 'mapped',
        ]);
    }

    public function manual(): static
    {
        return $this->state(fn () => ['provenance' => 'manual']);
    }

    public function exception(string $reason = 'Ambiguous mapping'): static
    {
        return $this->state(fn () => [
            'mapping_status'   => 'exception',
            'exception_reason' => $reason,
        ]);
    }
}
