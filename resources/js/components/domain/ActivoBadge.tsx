import { Circle16Regular, CheckmarkCircle16Regular } from '@fluentui/react-icons';
import { Badge } from '@/components/ui/Badge';

/** Estado de un registro de catálogo; usa la marca y no los colores de semáforo (5.3). */
export function ActivoBadge({ activo, femenino = false }: { activo: boolean; femenino?: boolean }) {
    const fin = femenino ? 'a' : 'o';

    return activo ? (
        <Badge tono="marca" icono={<CheckmarkCircle16Regular />}>
            Activ{fin}
        </Badge>
    ) : (
        <Badge icono={<Circle16Regular />}>Inactiv{fin}</Badge>
    );
}
