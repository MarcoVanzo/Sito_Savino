<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(5),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 5, 200),
            // Mai zero: con la giacenza a zero l'osservatore del magazzino
            // rifiuta il movimento di scarico e ogni test che compra qualcosa
            // cadeva una volta ogni cento, senza che niente fosse rotto. Chi
            // vuole un prodotto esaurito lo dichiara.
            'stock' => fake()->numberBetween(5, 100),
            'sku' => strtoupper(fake()->bothify('??-####')),
            'is_active' => true,
        ];
    }
}
