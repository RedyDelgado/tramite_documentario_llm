import { IcoArchivo16, IcoBuscarDocumento16, IcoCandado16, IcoCheck16, IcoCorrecto16, IcoDerivar16, IcoHistorial16, IcoProhibido16, IcoReloj16 } from '@/components/ui/iconos';
import type { ReactElement } from 'react';
import { Badge } from '@/components/ui/Badge';
import { Tooltip } from '@/components/ui/Tooltip';
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

/** Qué significa cada estado, para explicarlo al pasar el mouse a quien no conoce los términos. */
export const SIGNIFICADO_ESTADO: Record<EstadoExpediente, string> = {
    por_revisar: 'Llegó y espera que mesa de partes decida si es un trámite.',
    registrado: 'Ya tiene número de registro; falta derivarlo a un área.',
    derivado: 'Se envió a las áreas; esperan su respuesta.',
    en_atencion: 'Un área lo tomó y lo está atendiendo.',
    atendido: 'Todas las áreas lo atendieron; falta cerrarlo.',
    cerrado: 'Trámite concluido.',
    no_tramite: 'No era un trámite (boletín, publicidad, aviso automático): se archivó sin número. No se borra.',
    historico: 'Llegó antes de que el sistema empezara a operar: queda como referencia, sin plazo ni semáforo.',
    anulado: 'Se registró por error: conserva su número, que no se vuelve a usar.',
};

// Trámites vivos con la marca; el resto en neutro. Los colores semánticos son solo del semáforo (5.3).
const EN_CURSO: EstadoExpediente[] = ['registrado', 'derivado', 'en_atencion', 'atendido'];

export function EstadoBadge({ estado }: { estado: { valor: EstadoExpediente; etiqueta: string } }) {
    return (
        <Tooltip texto={SIGNIFICADO_ESTADO[estado.valor]}>
            <span tabIndex={0}>
                <Badge tono={EN_CURSO.includes(estado.valor) ? 'marca' : 'neutro'} icono={ICONOS[estado.valor]}>
                    {estado.etiqueta}
                </Badge>
            </span>
        </Tooltip>
    );
}
