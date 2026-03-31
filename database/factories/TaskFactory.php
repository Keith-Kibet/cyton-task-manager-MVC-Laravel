<?php

namespace Database\Factories;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->words(3, true).' '.fake()->uuid(),
            'due_date' => now()->addDays(rand(1, 30))->toDateString(),
            'priority' => fake()->randomElement(['low', 'medium', 'high']),
            'status' => 'pending',
        ];
    }
}
