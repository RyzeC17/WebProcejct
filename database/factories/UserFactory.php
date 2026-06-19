<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome_utente' => fake()->unique()->userName(),
            'nome' => fake()->firstName(),
            'cognome' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'attivo' => true,
            'data_iscrizione' => now(),
        ];
    }

    public function admin(): static
    {
        return $this->withRole('admin');
    }

    public function userRole(): static
    {
        return $this->withRole('user');
    }

    public function withRole(string $roleName): static
    {
        return $this->afterCreating(function (User $user) use ($roleName): void {
            $user->assignRole(Role::query()->firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]));
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this;
    }
}
