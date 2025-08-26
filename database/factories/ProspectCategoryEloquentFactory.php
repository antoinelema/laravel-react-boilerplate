<?php

namespace Database\Factories;

use App\__Infrastructure__\Eloquent\ProspectCategoryEloquent;
use App\__Infrastructure__\Eloquent\UserEloquent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\__Infrastructure__\Eloquent\ProspectCategoryEloquent>
 */
class ProspectCategoryEloquentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = ProspectCategoryEloquent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $colors = ['#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6', '#06b6d4', '#84cc16', '#f97316'];
        
        return [
            'user_id' => UserEloquent::factory(),
            'name' => $this->faker->unique()->words(2, true),
            'color' => $this->faker->randomElement($colors),
            'position' => $this->faker->numberBetween(0, 10),
        ];
    }

    /**
     * Indicate that the category should have a specific color.
     */
    public function withColor(string $color): static
    {
        return $this->state(fn (array $attributes) => [
            'color' => $color,
        ]);
    }

    /**
     * Indicate that the category should have a specific position.
     */
    public function withPosition(int $position): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => $position,
        ]);
    }

    /**
     * Indicate that the category should belong to a specific user.
     */
    public function forUser(UserEloquent $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}