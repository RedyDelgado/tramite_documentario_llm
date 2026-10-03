import { IcoCirculo16, IcoCorrecto16 } from '@/components/ui/iconos';
import { Badge } from '@/components/ui/Badge';

/** Estado de un registro de catálogo; usa la marca y no los colores de semáforo (5.3). */
export function ActivoBadge({ activo, femenino = false }: { activo: boolean; femenino?: boolean }) {
    const fin = femenino ? 'a' : 'o';

    return activo ? (
        <Badge tono="marca" icono={<IcoCorrecto16 />}>
            Activ{fin}
        </Badge>
    ) : (
        <Badge icono={<IcoCirculo16 />}>Inactiv{fin}</Badge>
    );
}
