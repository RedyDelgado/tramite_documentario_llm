<?php

namespace Database\Factories;

use App\Enums\EstadoExpediente;
use App\Enums\OrigenExpediente;
use App\Models\Expediente;
use App\Models\ExpedienteArea;
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

    /** Con área, la deja también como el área que lo atiende, igual que AtencionService al derivar. */
    public function configure(): static
    {
        return $this->afterCreating(function (Expediente $e) {
            if (! $e->area_principal_id) {
                return;
            }
            ExpedienteArea::create([
                'expediente_id' => $e->id,
                'area_id' => $e->area_principal_id,
                'responsable_id' => $e->responsable_id,
                'plazo_dias_aplicado' => $e->plazo_dias_aplicado,
                'fecha_limite' => $e->fecha_limite,
                'estado' => match ($e->estado) {
                    EstadoExpediente::Registrado, EstadoExpediente::Derivado => 'derivado',
                    EstadoExpediente::EnAtencion => 'en_atencion',
                    default => 'atendido',
                },
                'atendido_at' => $e->atendido_at,
            ]);
            // El semáforo depende de quién atiende cada área: se recalcula con la fila ya creada.
            $e->unsetRelation('atenciones')->save();
        });
    }
}
