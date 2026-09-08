<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

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
            'store_slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(4),
            'storefront_enabled' => true,
            'status' => 'active',
            'plan' => 'professional',
            'email' => fake()->unique()->companyEmail(),
            'phone' => fake()->phoneNumber(),
        ];
    }
}
