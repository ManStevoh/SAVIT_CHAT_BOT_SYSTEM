<?php

namespace Database\Factories;

use App\Models\Chat;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chat>
 */
class ChatFactory extends Factory
{
    protected $model = Chat::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'channel' => 'whatsapp',
            'channel_user_id' => fake()->numerify('2547########'),
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->numerify('2547########'),
            'status' => 'active',
            'ai_handled' => true,
        ];
    }
}
