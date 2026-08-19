<?php

namespace Database\Factories;

use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    protected $model = SocialAccount::class;

    public function definition(): array
    {
        return [
            'name' => 'Prima squadra',
            'page_id' => (string) $this->faker->unique()->numberBetween(100000000000000, 999999999999999),
            'page_name' => $this->faker->company(),
            'ig_account_id' => (string) $this->faker->unique()->numberBetween(100000000000000, 999999999999999),
            'ig_username' => $this->faker->userName(),
            'access_token' => 'token-di-prova',
            'token_expires_at' => null,
            'connected_at' => now(),
            'sort' => 0,
        ];
    }

    /** Account senza Instagram: resta solo la Pagina Facebook. */
    public function withoutInstagram(): static
    {
        return $this->state(fn (): array => ['ig_account_id' => null, 'ig_username' => null]);
    }

    /** Account mai collegato o scollegato dal pannello. */
    public function disconnected(): static
    {
        return $this->state(fn (): array => ['access_token' => null]);
    }
}
