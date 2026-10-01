import { CheckmarkCircle16Filled, Circle16Regular, Clock16Filled, ErrorCircle16Filled } from '@fluentui/react-icons';
import { Badge } from '@/components/ui/Badge';

export type Semaforo = 'verde' | 'amarillo' | 'rojo' | 'gris';

const SEMAFORO = {
    verde: { tono: 'ok', icono: <CheckmarkCircle16Filled />, texto: 'En plazo' },
    amarillo: { tono: 'aviso', icono: <Clock16Filled className="text-warn" />, texto: 'Por vencer' },
    rojo: { tono: 'peligro', icono: <ErrorCircle16Filled />, texto: 'Vencido' },
    gris: { tono: 'neutro', icono: <Circle16Regular className="text-neutral" />, texto: 'Pendiente' },
} as const;

/** El semáforo nunca depende solo del color: siempre lleva icono y texto (5.3). */
export function SemaforoBadge({ estado, texto }: { estado: Semaforo; texto?: string }) {
    const s = SEMAFORO[estado];

    return (
        <Badge tono={s.tono} icono={s.icono}>
            {texto ?? s.texto}
        </Badge>
    );
}
