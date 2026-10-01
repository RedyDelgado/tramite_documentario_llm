<?php

namespace Database\Factories;

use App\Models\Area;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => 'Área de '.fake()->unique()->word(),
            'descripcion' => fake()->sentence(),
            'palabras_clave' => fake()->words(3),
            'orden' => 0,
            'activa' => true,
        ];
    }
}
