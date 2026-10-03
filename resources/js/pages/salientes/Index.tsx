import { IcoAgregar, IcoEnviar } from '@/components/ui/iconos';
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
import { cerrarModal, rutaModal } from '@/lib/modal';
import type { Opcion, Paginado, Saliente } from '@/types';
import type { ComponentProps } from 'react';
import Formulario from './Form';
import SalienteShow, { type DetalleSalienteProps } from './Show';

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
    { clave: 'actualizado', titulo: 'Actualizado', sinCorte: true, ancho: '11rem', celda: (s) => formatearFechaHora(s.actualizado) },
];

type Props = {
    salientes: Paginado<Saliente>;
    filtros: Filtros;
    estados: Opcion<string>[];
    formulario?: Omit<ComponentProps<typeof Formulario>, 'onCerrar'>;
    detalle?: DetalleSalienteProps['saliente'];
};

export default function SalientesIndex({ salientes, filtros: iniciales, estados, formulario, detalle }: Props) {
    const { filtros, cambiar, cargando } = useFiltros<Filtros>(iniciales);

    return (
        <>
            {formulario && <Formulario {...formulario} onCerrar={() => cerrarModal('/salientes')} />}
            {detalle && <SalienteShow saliente={detalle} onCerrar={() => cerrarModal('/salientes')} />}
            <ListPage
                titulo="Documentos emitidos"
                descripcion="Oficios, cartas e informes que emite la institución. Ninguno sale sin aprobación; el número se asigna al aprobar."
                acciones={
                    <Link href={rutaModal('/salientes/create')} preserveScroll className={botonClases({ variante: 'primario' })}>
                        <IcoAgregar />
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
                    onElegirFila: (s) => router.visit(rutaModal(`/salientes/${s.id}`), { preserveScroll: true }),
                    cargando,
                    vacio: (
                        <EmptyState
                            icono={<IcoEnviar />}
                            titulo="Aún no hay documentos"
                            descripcion="Redacta uno desde aquí o desde un expediente, para responderlo."
                        />
                    ),
                }}
                paginacion={salientes}
            />
        </>
    );
}
