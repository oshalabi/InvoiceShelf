<?php

namespace Database\Factories;

use App\Domains\Accounts\Models\User;
use App\Domains\Receivables\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentMethodFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PaymentMethod::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'company_id' => User::find(1)->companies()->first()->id,
        ];
    }
}
