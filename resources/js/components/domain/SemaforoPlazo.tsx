import { SemaforoBadge, SIGNIFICADO_SEMAFORO, type Semaforo } from '@/components/domain/SemaforoBadge';
import { Tooltip } from '@/components/ui/Tooltip';
import { formatearFecha, hoyEnLima, textoVencimiento } from '@/lib/fechas';

/** Semáforo con el plazo en palabras («Vence en 2 días»): el color dice el estado y el texto, cuánto falta (8). */
export function SemaforoPlazo({ semaforo, fechaLimite }: { semaforo: Semaforo; fechaLimite: string | null }) {
    // En rojo sin haber vencido: el área no tiene quien lo atienda (SemaforoService).
    const sinResponsable = semaforo === 'rojo' && (!fechaLimite || fechaLimite >= hoyEnLima());
    const plazo = fechaLimite && semaforo !== 'gris' ? textoVencimiento(fechaLimite) : null;

    return (
        <span className="flex flex-col items-start gap-0.5">
            <Tooltip texto={SIGNIFICADO_SEMAFORO[semaforo]}>
                <span tabIndex={0}>
                    <SemaforoBadge estado={semaforo} texto={sinResponsable ? 'Sin responsable' : undefined} />
                </span>
            </Tooltip>
            {plazo && (
                <span className="text-sm whitespace-nowrap text-fg-muted" title={`Fecha límite: ${formatearFecha(fechaLimite)}`}>
                    {plazo}
                </span>
            )}
        </span>
    );
}
