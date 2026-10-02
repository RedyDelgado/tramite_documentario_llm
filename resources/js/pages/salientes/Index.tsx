import { Add20Regular, Send20Regular } from '@fluentui/react-icons';
import { Link, router } from '@inertiajs/react';
import type { Columna } from '@/components/data/DataTable';
import { SemaforoBadge } from '@/components/domain/SemaforoBadge';
import { ListPage } from '@/components/layouts/ListPage';
import { Badge } from '@/components/ui/Badge';
import { botonClases } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { Select } from '@/components/ui/Select';
import { useFiltros } from '@/hooks/useFiltros';
import { formatearFechaHora } from '@/lib/fechas';
import type { Opcion, Paginado, Saliente } from '@/types';

type Filtros = { estado?: string };

const columnas: Columna<Saliente>[] = [
    { clave: 'numero', titulo: 'Número', ancho: '16rem', celda: (s) => s.numero ?? <span className="text-fg-muted">Borrador</span> },
    {
        clave: 'asunto',
        titulo: 'Asunto',
        celda: (s) => (
            <div className="min-w-64">
                <p className="font-semibold">{s.asunto}</p>
                <p className="text-sm text-fg-muted">
                    {s.tipo} · {s.area}
                    {s.expediente && ` · responde a ${s.expediente.numero_registro ?? 'expediente'}`}
                </p>
            </div>
        ),
    },
    {
        clave: 'estado',
        titulo: 'Estado',
        ancho: '11rem',
        celda: (s) => (
            <span className="flex flex-wrap gap-1">
                <Badge tono={s.estado.valor === 'enviado' ? 'ok' : 'neutro'}>{s.estado.etiqueta}</Badge>
                {s.rebotes > 0 && <Badge tono="peligro">{s.rebotes === 1 ? '1 rebote' : `${s.rebotes} rebotes`}</Badge>}
            </span>
        ),
    },
    {
        clave: 'respuesta',
        titulo: 'Respuesta',
        ancho: '10rem',
        celda: (s) =>
            s.respondido_at ? (
                <Badge tono="ok">Respondido</Badge>
            ) : s.semaforo ? (
                <SemaforoBadge estado={s.semaforo} />
            ) : (
                <span className="text-fg-muted">—</span>
            ),
    },
    { clave: 'actualizado', titulo: 'Actualizado', ancho: '11rem', celda: (s) => formatearFechaHora(s.actualizado) },
];

type Props = { salientes: Paginado<Saliente>; filtros: Filtros; estados: Opcion<string>[] };

export default function SalientesIndex({ salientes, filtros: iniciales, estados }: Props) {
    const { filtros, cambiar, cargando } = useFiltros<Filtros>(iniciales);

    return (
        <ListPage
            titulo="Documentos emitidos"
            descripcion="Oficios, cartas e informes que emite la institución. Ninguno sale sin aprobación; el número se asigna al aprobar."
            acciones={
                <Link href="/salientes/create" className={botonClases({ variante: 'primario' })}>
                    <Add20Regular />
                    Redactar documento
                </Link>
            }
            filtros={
                <Select
                    aria-label="Estado"
                    className="w-40"
                    vacia="Todos los estados"
                    opciones={estados}
                    value={filtros.estado ?? ''}
                    onChange={(e) => cambiar({ estado: e.target.value })}
                />
            }
            tabla={{
                titulo: 'Documentos emitidos',
                columnas,
                filas: salientes.data,
                claveFila: (s) => s.id,
                onElegirFila: (s) => router.visit(`/salientes/${s.id}`),
                cargando,
                vacio: (
                    <EmptyState
                        icono={<Send20Regular />}
                        titulo="Aún no hay documentos"
                        descripcion="Redacta uno desde aquí o desde un expediente, para responderlo."
                    />
                ),
            }}
            paginacion={salientes}
        />
    );
}
