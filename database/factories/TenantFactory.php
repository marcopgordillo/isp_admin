<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();
        return [
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'subdomain' => Str::slug($name) . '-' . fake()->randomNumber(3),
            'plan' => fake()->randomElement(['basic', 'advanced']),
            'db_name' => 'tenant_' . Str::random(10),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the tenant has a basic plan.
     */
    public function basic(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => 'basic',
        ]);
    }

    /**
     * Indicate that the tenant has an advanced plan.
     */
    public function advanced(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => 'advanced',
        ]);
    }
}
