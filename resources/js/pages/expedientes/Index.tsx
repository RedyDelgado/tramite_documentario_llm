import { Attach16Regular, DocumentBulletList20Regular, Open20Regular, Search20Regular } from '@fluentui/react-icons';
import { Link } from '@inertiajs/react';
import { useState } from 'react';
import type { Columna } from '@/components/data/DataTable';
import { DetalleLista } from '@/components/data/DetalleLista';
import { AccionesRegistro } from '@/components/domain/AccionesRegistro';
import { EstadoBadge } from '@/components/domain/EstadoBadge';
import { ListPage } from '@/components/layouts/ListPage';
import { Badge } from '@/components/ui/Badge';
import { botonClases } from '@/components/ui/Button';
import { Drawer } from '@/components/ui/Drawer';
import { EmptyState } from '@/components/ui/EmptyState';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { useFiltros } from '@/hooks/useFiltros';
import { formatearFechaHora } from '@/lib/fechas';
import type { ExpedienteFila, Opcion, Paginado } from '@/types';

type Filtros = { q?: string; estado?: string; dir?: 'asc' | 'desc' };

function Remitente({ e }: { e: ExpedienteFila }) {
    return (
        <span className="flex flex-wrap items-center gap-1">
            {e.remitente_nombre ?? e.remitente_email}
            {e.remitente_por_confirmar && <Badge>Remitente por confirmar</Badge>}
        </span>
    );
}

const columnas: Columna<ExpedienteFila>[] = [
    {
        clave: 'numero',
        titulo: 'N° registro',
        ancho: '8rem',
        celda: (e) => e.numero_registro ?? <span className="text-fg-muted">—</span>,
    },
    {
        clave: 'asunto',
        titulo: 'Asunto',
        celda: (e) => (
            <div className="min-w-64">
                <p className="font-semibold">{e.asunto}</p>
                <p className="text-sm text-fg-muted">
                    <Remitente e={e} />
                </p>
            </div>
        ),
    },
    { clave: 'fecha', titulo: 'Ingreso', ordenable: true, ancho: '11rem', celda: (e) => formatearFechaHora(e.fecha_ingreso) },
    { clave: 'area', titulo: 'Área', ancho: '12rem', celda: (e) => e.area ?? <span className="text-fg-muted">Sin asignar</span> },
    {
        clave: 'adjuntos',
        titulo: 'Adjuntos',
        alinear: 'derecha',
        ancho: '6rem',
        celda: (e) =>
            e.documentos_count ? (
                <span className="inline-flex items-center gap-1">
                    <Attach16Regular aria-hidden />
                    {e.documentos_count}
                </span>
            ) : (
                <span className="text-fg-muted">—</span>
            ),
    },
    { clave: 'estado', titulo: 'Estado', ancho: '10rem', celda: (e) => <EstadoBadge estado={e.estado} /> },
];

type Props = { expedientes: Paginado<ExpedienteFila>; filtros: Filtros; estados: Opcion<string>[] };

export default function ExpedientesIndex({ expedientes, filtros: iniciales, estados }: Props) {
    const { filtros, cambiar, cargando } = useFiltros<Filtros>(iniciales);
    const [elegidoId, setElegidoId] = useState<number | null>(null);
    const elegido = expedientes.data.find((e) => e.id === elegidoId) ?? null;
    const hayFiltros = Boolean(filtros.q || filtros.estado);

    return (
        <ListPage
            titulo="Expedientes"
            descripcion="Todo lo que ingresa por el buzón central: revisa, registra como trámite o archiva lo que no lo es."
            filtros={
                <>
                    <Select
                        aria-label="Estado"
                        className="w-40"
                        vacia="Todos los estados"
                        opciones={estados}
                        value={filtros.estado ?? ''}
                        onChange={(e) => cambiar({ estado: e.target.value })}
                    />
                    <Input
                        type="search"
                        aria-label="Filtrar la lista"
                        placeholder="Asunto, remitente, código o texto"
                        iconoInicio={<Search20Regular />}
                        className="w-72"
                        value={filtros.q ?? ''}
                        onChange={(e) => cambiar({ q: e.target.value }, { diferido: true })}
                    />
                </>
            }
            tabla={{
                titulo: 'Expedientes',
                columnas,
                filas: expedientes.data,
                claveFila: (e) => e.id,
                // Con búsqueda manda la relevancia; sin ella, la fecha de ingreso.
                orden: filtros.q ? undefined : { clave: 'fecha', dir: filtros.dir ?? 'desc' },
                onOrdenar: filtros.q ? undefined : () => cambiar({ dir: filtros.dir === 'asc' ? 'desc' : 'asc' }),
                seleccionada: elegidoId,
                onElegirFila: (e) => setElegidoId(e.id),
                cargando,
                vacio: (
                    <EmptyState
                        icono={<DocumentBulletList20Regular />}
                        titulo={hayFiltros ? 'Ningún expediente coincide' : 'Aún no hay expedientes'}
                        descripcion={hayFiltros ? 'Prueba con otras palabras o quita el filtro de estado.' : 'Los correos del buzón central aparecerán aquí al ingresar.'}
                    />
                ),
            }}
            paginacion={expedientes}
            detalle={
                elegido && (
                    <Drawer
                        abierto
                        onCambiar={(abierto) => !abierto && setElegidoId(null)}
                        titulo={elegido.numero_registro ?? elegido.asunto}
                        subtitulo={<EstadoBadge estado={elegido.estado} />}
                        acciones={
                            <div className="flex flex-wrap gap-2">
                                <Link href={`/expedientes/${elegido.id}`} className={botonClases()}>
                                    <Open20Regular />
                                    Abrir
                                </Link>
                                <AccionesRegistro expediente={elegido} />
                            </div>
                        }
                    >
                        <DetalleLista
                            items={[
                                { etiqueta: 'Asunto', valor: elegido.asunto },
                                { etiqueta: 'Remitente', valor: <Remitente e={elegido} /> },
                                { etiqueta: 'Correo del remitente', valor: elegido.remitente_email ?? '—' },
                                { etiqueta: 'Ingreso', valor: formatearFechaHora(elegido.fecha_ingreso) },
                                { etiqueta: 'Código', valor: elegido.codigo ?? 'Se asigna al registrar como trámite' },
                                { etiqueta: 'Área', valor: elegido.area ?? 'Sin asignar' },
                            ]}
                        />
                    </Drawer>
                )
            }
        />
    );
}
