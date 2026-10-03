import { formatearFechaHora } from '@/lib/fechas';
import type { EventoHistorial } from '@/types';

/** Historial del expediente según la auditoría, del más antiguo al más reciente. */
export function LineaTiempo({ eventos }: { eventos: EventoHistorial[] }) {
    if (eventos.length === 0) {
        return <p className="text-base text-fg-muted">Sin movimientos.</p>;
    }

    return (
        <ol className="relative ml-1 flex flex-col gap-5 border-l-2 border-primary-100 pl-5">
            {eventos.map((e) => (
                <li key={e.id} className="relative">
                    <span aria-hidden className="absolute top-1.5 -left-[27px] size-3 rounded-full border-2 border-surface bg-primary-600" />
                    <p className="text-base font-medium text-fg">{e.accion}</p>
                    {e.detalle && <p className="text-base text-fg">{e.detalle}</p>}
                    <p className="text-sm text-fg-muted">
                        <time dateTime={e.fecha}>{formatearFechaHora(e.fecha)}</time> · {e.usuario}
                    </p>
                </li>
            ))}
        </ol>
    );
}
