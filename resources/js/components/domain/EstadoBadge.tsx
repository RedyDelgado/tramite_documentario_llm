import { IcoArchivo16, IcoBuscarDocumento16, IcoCandado16, IcoCheck16, IcoCorrecto16, IcoDerivar16, IcoHistorial16, IcoProhibido16, IcoReloj16 } from '@/components/ui/iconos';
import type { ReactElement } from 'react';
import { Badge } from '@/components/ui/Badge';
import type { EstadoExpediente } from '@/types';

const ICONOS: Record<EstadoExpediente, ReactElement> = {
    por_revisar: <IcoBuscarDocumento16 />,
    registrado: <IcoCorrecto16 />,
    derivado: <IcoDerivar16 />,
    en_atencion: <IcoReloj16 />,
    atendido: <IcoCheck16 />,
    cerrado: <IcoCandado16 />,
    no_tramite: <IcoArchivo16 />,
    historico: <IcoHistorial16 />,
    anulado: <IcoProhibido16 />,
};

// Trámites vivos con la marca; el resto en neutro. Los colores semánticos son solo del semáforo (5.3).
const EN_CURSO: EstadoExpediente[] = ['registrado', 'derivado', 'en_atencion', 'atendido'];

export function EstadoBadge({ estado }: { estado: { valor: EstadoExpediente; etiqueta: string } }) {
    return (
        <Badge tono={EN_CURSO.includes(estado.valor) ? 'marca' : 'neutro'} icono={ICONOS[estado.valor]}>
            {estado.etiqueta}
        </Badge>
    );
}
