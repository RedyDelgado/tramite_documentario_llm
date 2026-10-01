<?php

namespace Database\Factories;

use App\Enums\EstadoExpediente;
use App\Enums\OrigenExpediente;
use App\Models\Expediente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expediente>
 */
class ExpedienteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'origen' => OrigenExpediente::Correo,
            'estado' => EstadoExpediente::PorRevisar,
            'asunto' => 'Oficio N° '.fake()->numberBetween(1, 999).' sobre '.fake()->words(3, true),
            'remitente_nombre' => fake()->name(),
            'remitente_email' => fake()->safeEmail(),
            'fecha_ingreso' => now(),
        ];
    }
}
