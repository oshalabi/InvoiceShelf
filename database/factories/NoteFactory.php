<?php

namespace Database\Factories;

use App\Domains\Accounts\Models\User;
use App\Domains\Metadata\Models\Note;
use Illuminate\Database\Eloquent\Factories\Factory;

class NoteFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Note::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement(['Invoice', 'Estimate', 'Payment']),
            'name' => $this->faker->word(),
            'notes' => $this->faker->text(),
            'company_id' => User::find(1)->companies()->first()->id,
            'is_default' => $this->faker->boolean(),
        ];
    }
}
