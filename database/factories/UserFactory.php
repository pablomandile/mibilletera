<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // Aceptó la versión vigente: si no, el middleware AceptoTerminos
            // frenaría a todos los usuarios de prueba.
            'terminos_version' => config('legales.version'),
            'terminos_aceptados_en' => now(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function sinAceptarTerminos(): static
    {
        return $this->state(fn (array $attributes) => [
            'terminos_version' => null,
            'terminos_aceptados_en' => null,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
