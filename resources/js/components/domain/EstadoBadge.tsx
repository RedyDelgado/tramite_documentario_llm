import {
    Archive16Regular,
    ArrowForward16Regular,
    Checkmark16Regular,
    CheckmarkCircle16Regular,
    Clock16Regular,
    DocumentSearch16Regular,
    History16Regular,
    LockClosed16Regular,
    Prohibited16Regular,
} from '@fluentui/react-icons';
import type { ReactElement } from 'react';
import { Badge } from '@/components/ui/Badge';
import type { EstadoExpediente } from '@/types';

const ICONOS: Record<EstadoExpediente, ReactElement> = {
    por_revisar: <DocumentSearch16Regular />,
    registrado: <CheckmarkCircle16Regular />,
    derivado: <ArrowForward16Regular />,
    en_atencion: <Clock16Regular />,
    atendido: <Checkmark16Regular />,
    cerrado: <LockClosed16Regular />,
    no_tramite: <Archive16Regular />,
    historico: <History16Regular />,
    anulado: <Prohibited16Regular />,
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
