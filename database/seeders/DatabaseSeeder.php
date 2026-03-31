<?php

namespace Database\Seeders;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin Demo',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $user = User::query()->create([
            'name' => 'User Demo',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);

        Task::query()->create([
            'user_id' => $user->id,
            'title' => 'Seed task high',
            'due_date' => now()->addDay()->toDateString(),
            'priority' => 'high',
            'status' => 'pending',
        ]);

        Task::query()->create([
            'user_id' => $admin->id,
            'title' => 'Admin seed task',
            'due_date' => now()->addDays(2)->toDateString(),
            'priority' => 'medium',
            'status' => 'in_progress',
        ]);
    }
}
