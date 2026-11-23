import { Badge } from '@/components/ui/Badge';
import { formatearFecha, formatearFechaHora, textoVencimiento } from '@/lib/fechas';
import type { AtencionArea } from '@/types';

const TONO = { derivado: 'neutro', en_atencion: 'marca', atendido: 'ok' } as const;

/** Cada área que atiende el expediente con su estado, quién la atiende, su plazo y cómo lo atendió. */
export function AreasAtencion({ atenciones }: { atenciones: AtencionArea[] }) {
    if (atenciones.length === 0) return <span className="text-fg-muted">Sin asignar</span>;

    return (
        <ul className="flex flex-col gap-2">
            {atenciones.map((a) => (
                <li key={a.area_id}>
                    <span className="flex flex-wrap items-center gap-1.5">
                        <span className="font-medium">{a.area}</span>
                        <Badge tono={TONO[a.estado.valor]}>{a.estado.etiqueta}</Badge>
                    </span>
                    <span className="block text-sm text-fg-muted">
                        {a.responsable ?? 'Quien coordina el área'} ·{' '}
                        {a.fecha_limite ? `hasta el ${formatearFecha(a.fecha_limite)}${a.plazo_dias ? ` (plazo de ${a.plazo_dias} días)` : ''}` : 'sin plazo'}
                        {/* Lo pendiente dice cuánto le falta; lo atendido ya no corre. */}
                        {a.fecha_limite && a.estado.valor !== 'atendido' && <span className="font-medium text-fg"> · {textoVencimiento(a.fecha_limite)}</span>}
                    </span>
                    {a.atendido_at && (
                        <span className="block text-sm text-fg-muted">
                            Atendido{a.atendido_por ? ` por ${a.atendido_por}` : ''} el {formatearFechaHora(a.atendido_at)}
                            {a.nota ? `: ${a.nota}` : ''}
                        </span>
                    )}
                </li>
            ))}
        </ul>
    );
}
