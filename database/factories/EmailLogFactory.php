<?php

namespace Database\Factories;

use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use App\Platform\Mail\Models\EmailLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmailLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = EmailLog::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $mailable = $this->faker->randomElement([Invoice::class, Estimate::class, Payment::class]);

        return [
            'from' => $this->faker->unique()->safeEmail(),
            'to' => $this->faker->unique()->safeEmail(),
            'subject' => $this->faker->sentence(),
            'body' => $this->faker->text(),
            'mailable_type' => (new $mailable)->getMorphClass(),
            'mailable_id' => $mailable::factory(),
        ];
    }
}
