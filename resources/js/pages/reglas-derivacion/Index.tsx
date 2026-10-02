import { Add20Regular, ArrowRouting20Regular, Edit20Regular, Search20Regular } from '@fluentui/react-icons';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { Columna } from '@/components/data/DataTable';
import { DetalleLista } from '@/components/data/DetalleLista';
import { ListaEtiquetas } from '@/components/data/ListaEtiquetas';
import { ActivoBadge } from '@/components/domain/ActivoBadge';
import { ListPage } from '@/components/layouts/ListPage';
import { Button, botonClases } from '@/components/ui/Button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Drawer } from '@/components/ui/Drawer';
import { EmptyState } from '@/components/ui/EmptyState';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { useFiltros } from '@/hooks/useFiltros';
import { formatearFechaHora } from '@/lib/fechas';
import type { Opcion, Paginado, ReglaDerivacion } from '@/types';

type Filtros = { q?: string; area?: string; estado?: string };

const condiciones = (r: ReglaDerivacion) =>
    [r.tipo && `Tipo: ${r.tipo}`, r.palabras_clave.length > 0 && 'Palabras clave', r.remitentes.length > 0 && 'Remitentes'].filter(Boolean).join(' · ');

const columnas: Columna<ReglaDerivacion>[] = [
    { clave: 'prioridad', titulo: 'Prioridad', ancho: '6rem', celda: (r) => r.prioridad },
    {
        clave: 'nombre',
        titulo: 'Regla',
        celda: (r) => (
            <div className="min-w-48">
                <p className="font-semibold">{r.nombre}</p>
                <p className="text-sm text-fg-muted">{condiciones(r)}</p>
            </div>
        ),
    },
    {
        clave: 'destino',
        titulo: 'Deriva a',
        celda: (r) => (
            <div>
                <p>{r.area_destino}</p>
                {r.responsable && <p className="text-sm text-fg-muted">{r.responsable}</p>}
            </div>
        ),
    },
    { clave: 'estado', titulo: 'Estado', ancho: '8rem', celda: (r) => <ActivoBadge activo={r.activa} femenino /> },
];

type Props = { reglas: Paginado<ReglaDerivacion>; filtros: Filtros; opcionesArea: Opcion<number>[] };

export default function ReglasDerivacionIndex({ reglas, filtros: iniciales, opcionesArea }: Props) {
    const { filtros, cambiar, cargando } = useFiltros<Filtros>(iniciales);
    const [elegidaId, setElegidaId] = useState<number | null>(null);
    const [confirmando, setConfirmando] = useState(false);
    const [procesando, setProcesando] = useState(false);
    const elegida = reglas.data.find((r) => r.id === elegidaId) ?? null;
    const hayFiltros = Boolean(filtros.q || filtros.area || filtros.estado);

    const cambiarEstado = (regla: ReglaDerivacion) =>
        router.patch(
            `/reglas-derivacion/${regla.id}/estado`,
            { activa: !regla.activa },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => setProcesando(true),
                onFinish: () => {
                    setProcesando(false);
                    setConfirmando(false);
                },
            },
        );

    return (
        <ListPage
            titulo="Reglas de derivación"
            descripcion="Sugieren a qué área derivar según tipo, palabras del asunto o remitente. Se aplica la primera activa, por prioridad."
            acciones={
                <Link href="/reglas-derivacion/create" className={botonClases({ variante: 'primario' })}>
                    <Add20Regular />
                    Nueva regla
                </Link>
            }
            filtros={
                <>
                    <Select
                        aria-label="Estado"
                        className="w-36"
                        vacia="Todas"
                        opciones={[
                            { value: 'activas', label: 'Activas' },
                            { value: 'inactivas', label: 'Inactivas' },
                        ]}
                        value={filtros.estado ?? ''}
                        onChange={(e) => cambiar({ estado: e.target.value })}
                    />
                    <Select
                        aria-label="Área de destino"
                        className="w-56"
                        vacia="Todas las áreas"
                        opciones={opcionesArea}
                        value={filtros.area ?? ''}
                        onChange={(e) => cambiar({ area: e.target.value })}
                    />
                    <Input
                        type="search"
                        aria-label="Buscar por nombre"
                        placeholder="Buscar por nombre"
                        iconoInicio={<Search20Regular />}
                        className="w-64"
                        value={filtros.q ?? ''}
                        onChange={(e) => cambiar({ q: e.target.value }, { diferido: true })}
                    />
                </>
            }
            tabla={{
                titulo: 'Reglas de derivación',
                columnas,
                filas: reglas.data,
                claveFila: (r) => r.id,
                seleccionada: elegidaId,
                onElegirFila: (r) => setElegidaId(r.id),
                cargando,
                vacio: (
                    <EmptyState
                        icono={<ArrowRouting20Regular />}
                        titulo={hayFiltros ? 'No hay reglas que coincidan con los filtros' : 'Aún no hay reglas de derivación'}
                        descripcion={hayFiltros ? 'Cambia la búsqueda, el área o el estado.' : 'Por ejemplo: los convenios van a Cooperación; lo que llega de la SUNEDU, a Dirección.'}
                    />
                ),
            }}
            paginacion={reglas}
            detalle={
                elegida && (
                    <Drawer
                        abierto
                        onCambiar={(abierto) => !abierto && setElegidaId(null)}
                        titulo={elegida.nombre}
                        subtitulo={<ActivoBadge activo={elegida.activa} femenino />}
                        acciones={
                            <>
                                <Link href={`/reglas-derivacion/${elegida.id}/edit`} className={botonClases()}>
                                    <Edit20Regular />
                                    Editar
                                </Link>
                                {elegida.activa ? (
                                    <Button onClick={() => setConfirmando(true)}>Desactivar</Button>
                                ) : (
                                    <Button cargando={procesando} onClick={() => cambiarEstado(elegida)}>
                                        Activar
                                    </Button>
                                )}
                            </>
                        }
                    >
                        <DetalleLista
                            items={[
                                { etiqueta: 'Prioridad', valor: elegida.prioridad },
                                { etiqueta: 'Tipo de trámite', valor: elegida.tipo ?? 'Cualquiera' },
                                {
                                    etiqueta: 'Palabras clave',
                                    valor: elegida.palabras_clave.length > 0 ? <ListaEtiquetas lista={elegida.palabras_clave} /> : 'Cualquiera',
                                },
                                { etiqueta: 'Remitentes', valor: elegida.remitentes.length > 0 ? <ListaEtiquetas lista={elegida.remitentes} /> : 'Cualquiera' },
                                { etiqueta: 'Área de destino', valor: elegida.area_destino },
                                { etiqueta: 'Responsable', valor: elegida.responsable ?? 'Lo asigna el área' },
                                { etiqueta: 'Última actualización', valor: formatearFechaHora(elegida.actualizado) },
                            ]}
                        />
                        <ConfirmDialog
                            abierto={confirmando}
                            onCambiar={setConfirmando}
                            titulo={`¿Desactivar «${elegida.nombre}»?`}
                            descripcion="Dejará de sugerir derivaciones. Las derivaciones ya hechas no cambian."
                            confirmar="Desactivar"
                            peligro
                            cargando={procesando}
                            onConfirmar={() => cambiarEstado(elegida)}
                        />
                    </Drawer>
                )
            }
        />
    );
}
