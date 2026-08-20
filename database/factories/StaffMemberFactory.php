<?php

namespace Database\Factories;

use App\Enums\StaffType;
use App\Models\StaffMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffMember>
 */
class StaffMemberFactory extends Factory
{
    protected $model = StaffMember::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'role' => ['it' => 'Primo Allenatore', 'en' => 'Head Coach'],
            'type' => StaffType::cases()[0],
            'sort_order' => fake()->numberBetween(0, 50),
        ];
    }
}
