import { MailAlert20Regular } from '@fluentui/react-icons';
import type { Columna } from '@/components/data/DataTable';
import { ListPage } from '@/components/layouts/ListPage';
import { Badge } from '@/components/ui/Badge';
import { EmptyState } from '@/components/ui/EmptyState';
import { Select } from '@/components/ui/Select';
import { useFiltros } from '@/hooks/useFiltros';
import { formatearFechaHora } from '@/lib/fechas';
import type { Opcion, Paginado } from '@/types';

type Estado = 'pendiente' | 'enviado' | 'fallido' | 'rebotado';

/** NotificacionController@index. */
type Notificacion = { id: number; fecha: string; email: string; tipo: string; expedientes: number; estado: Estado; detalle: string | null };

type Filtros = { estado?: string };

const ESTADO: Record<Estado, { texto: string; tono: 'neutro' | 'ok' | 'peligro' }> = {
    pendiente: { texto: 'Pendiente', tono: 'neutro' },
    enviado: { texto: 'Enviado', tono: 'ok' },
    fallido: { texto: 'Fallido', tono: 'peligro' },
    rebotado: { texto: 'Rebotado', tono: 'peligro' },
};

const columnas: Columna<Notificacion>[] = [
    { clave: 'fecha', titulo: 'Fecha', ancho: '11rem', celda: (n) => formatearFechaHora(n.fecha) },
    { clave: 'email', titulo: 'Destinatario', celda: (n) => n.email },
    { clave: 'tipo', titulo: 'Tipo', ancho: '10rem', celda: (n) => `${n.tipo} (${n.expedientes})` },
    { clave: 'estado', titulo: 'Estado', ancho: '7rem', celda: (n) => <Badge tono={ESTADO[n.estado].tono}>{ESTADO[n.estado].texto}</Badge> },
    { clave: 'detalle', titulo: 'Motivo', celda: (n) => n.detalle ?? <span className="text-fg-muted">—</span> },
];

type Props = { notificaciones: Paginado<Notificacion>; filtros: Filtros; estados: Opcion<string>[] };

export default function NotificacionesIndex({ notificaciones, filtros: iniciales, estados }: Props) {
    const { filtros, cambiar, cargando } = useFiltros<Filtros>(iniciales);

    return (
        <ListPage
            titulo="Notificaciones"
            descripcion="Avisos que el sistema envía a las personas (resumen diario) y si llegaron. Un rebote suele ser una dirección mal escrita o una cuenta dada de baja."
            filtros={
                <Select
                    aria-label="Estado"
                    className="w-48"
                    vacia="Todos los estados"
                    opciones={estados}
                    value={filtros.estado ?? ''}
                    onChange={(e) => cambiar({ estado: e.target.value })}
                />
            }
            tabla={{
                titulo: 'Notificaciones',
                columnas,
                filas: notificaciones.data,
                claveFila: (n) => n.id,
                cargando,
                vacio: <EmptyState icono={<MailAlert20Regular />} titulo="Aún no se envió ninguna notificación" descripcion="El resumen diario sale los días laborables a las 07:30, solo a coordinadores con pendientes." />,
            }}
            paginacion={notificaciones}
        />
    );
}
