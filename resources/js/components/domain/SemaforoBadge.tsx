import { IcoAlerta16, IcoCirculo16, IcoCorrecto16, IcoReloj16 } from '@/components/ui/iconos';
import { Badge } from '@/components/ui/Badge';

export type Semaforo = 'verde' | 'amarillo' | 'rojo' | 'gris';

const SEMAFORO = {
    verde: { tono: 'ok', icono: <IcoCorrecto16 />, texto: 'En plazo' },
    amarillo: { tono: 'aviso', icono: <IcoReloj16 className="text-warn" />, texto: 'Por vencer' },
    rojo: { tono: 'peligro', icono: <IcoAlerta16 />, texto: 'Vencido' },
    gris: { tono: 'neutro', icono: <IcoCirculo16 className="text-neutral" />, texto: 'Pendiente' },
} as const;

/** Qué significa cada color (SemaforoService, sección 8), para explicarlo al pasar el mouse. */
export const SIGNIFICADO_SEMAFORO: Record<Semaforo, string> = {
    rojo: 'Pasó su fecha límite, o su área no tiene quien lo atienda.',
    amarillo: 'Le queda poco plazo, o lleva varios días sin movimiento.',
    verde: 'Avanza a tiempo: dentro del plazo y con movimiento reciente.',
    gris: 'Aún no se deriva: está por revisar o recién registrado.',
};

/** El semáforo nunca depende solo del color: siempre lleva icono y texto (5.3). */
export function SemaforoBadge({ estado, texto }: { estado: Semaforo; texto?: string }) {
    const s = SEMAFORO[estado];

    return (
        <Badge tono={s.tono} icono={s.icono}>
            {texto ?? s.texto}
        </Badge>
    );
}
