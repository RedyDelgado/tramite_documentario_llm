import { IcoAlerta16, IcoCirculo16, IcoCorrecto16, IcoReloj16 } from '@/components/ui/iconos';
import { Badge } from '@/components/ui/Badge';

export type Semaforo = 'verde' | 'amarillo' | 'rojo' | 'gris';

const SEMAFORO = {
    verde: { tono: 'ok', icono: <IcoCorrecto16 />, texto: 'En plazo' },
    amarillo: { tono: 'aviso', icono: <IcoReloj16 className="text-warn" />, texto: 'Por vencer' },
    rojo: { tono: 'peligro', icono: <IcoAlerta16 />, texto: 'Vencido' },
    gris: { tono: 'neutro', icono: <IcoCirculo16 className="text-neutral" />, texto: 'Pendiente' },
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
