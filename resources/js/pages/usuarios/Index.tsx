import { Add20Regular, Edit20Regular, People20Regular, Search20Regular } from '@fluentui/react-icons';
import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { Columna } from '@/components/data/DataTable';
import { DetalleLista } from '@/components/data/DetalleLista';
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
import type { Opcion, Paginado, UsuarioFila } from '@/types';

type Filtros = { q?: string; rol?: string; estado?: string; orden?: string; dir?: 'asc' | 'desc' };

const columnas: Columna<UsuarioFila>[] = [
    {
        clave: 'nombre',
        titulo: 'Nombre',
        ordenable: true,
        celda: (u) => (
            <div className="min-w-48">
                <p className="font-semibold">{u.name}</p>
                <p className="text-sm text-fg-muted">{u.email}</p>
            </div>
        ),
    },
    { clave: 'rol', titulo: 'Rol', celda: (u) => u.rol_etiqueta ?? <span className="text-fg-muted">Sin rol</span> },
    { clave: 'estado', titulo: 'Estado', ancho: '8rem', celda: (u) => <ActivoBadge activo={u.activo} /> },
];

type Props = { usuarios: Paginado<UsuarioFila>; filtros: Filtros; opcionesRol: Opcion<string>[] };

export default function UsuariosIndex({ usuarios, filtros: iniciales, opcionesRol }: Props) {
    const { auth } = usePage().props;
    const { filtros, cambiar, cargando } = useFiltros<Filtros>(iniciales);
    const [elegidoId, setElegidoId] = useState<number | null>(null);
    const [confirmando, setConfirmando] = useState(false);
    const [procesando, setProcesando] = useState(false);
    const elegido = usuarios.data.find((u) => u.id === elegidoId) ?? null;
    const hayFiltros = Boolean(filtros.q || filtros.rol || filtros.estado);

    const ordenar = (clave: string) =>
        cambiar({ orden: clave, dir: (filtros.orden ?? 'nombre') === clave && filtros.dir !== 'desc' ? 'desc' : 'asc' });

    const cambiarEstado = (usuario: UsuarioFila) =>
        router.patch(
            `/usuarios/${usuario.id}/estado`,
            { activo: !usuario.activo },
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
            titulo="Usuarios"
            descripcion="Solo ingresan con Google las cuentas registradas aquí y activas; el rol define qué ven y qué pueden hacer."
            acciones={
                <Link href="/usuarios/create" className={botonClases({ variante: 'primario' })}>
                    <Add20Regular />
                    Nuevo usuario
                </Link>
            }
            filtros={
                <>
                    <Select
                        aria-label="Rol"
                        className="w-40"
                        vacia="Todos los roles"
                        opciones={opcionesRol}
                        value={filtros.rol ?? ''}
                        onChange={(e) => cambiar({ rol: e.target.value })}
                    />
                    <Select
                        aria-label="Estado"
                        className="w-36"
                        vacia="Todos"
                        opciones={[
                            { value: 'activos', label: 'Activos' },
                            { value: 'inactivos', label: 'Inactivos' },
                        ]}
                        value={filtros.estado ?? ''}
                        onChange={(e) => cambiar({ estado: e.target.value })}
                    />
                    <Input
                        type="search"
                        aria-label="Buscar por nombre o correo"
                        placeholder="Buscar por nombre o correo"
                        iconoInicio={<Search20Regular />}
                        className="w-64"
                        value={filtros.q ?? ''}
                        onChange={(e) => cambiar({ q: e.target.value }, { diferido: true })}
                    />
                </>
            }
            tabla={{
                titulo: 'Usuarios',
                columnas,
                filas: usuarios.data,
                claveFila: (u) => u.id,
                orden: { clave: filtros.orden ?? 'nombre', dir: filtros.dir ?? 'asc' },
                onOrdenar: ordenar,
                seleccionada: elegidoId,
                onElegirFila: (u) => setElegidoId(u.id),
                cargando,
                vacio: (
                    <EmptyState
                        icono={<People20Regular />}
                        titulo={hayFiltros ? 'No hay usuarios que coincidan con los filtros' : 'Aún no hay usuarios'}
                        descripcion={hayFiltros ? 'Cambia la búsqueda, el rol o el estado.' : 'Registra a las personas que usarán el sistema.'}
                    />
                ),
            }}
            paginacion={usuarios}
            detalle={
                elegido && (
                    <Drawer
                        abierto
                        onCambiar={(abierto) => !abierto && setElegidoId(null)}
                        titulo={elegido.name}
                        subtitulo={<ActivoBadge activo={elegido.activo} />}
                        acciones={
                            <>
                                <Link href={`/usuarios/${elegido.id}/edit`} className={botonClases()}>
                                    <Edit20Regular />
                                    Editar
                                </Link>
                                {/* Nadie se desactiva a sí mismo; el servidor también lo impide. */}
                                {elegido.id !== auth.user?.id &&
                                    (elegido.activo ? (
                                        <Button onClick={() => setConfirmando(true)}>Desactivar</Button>
                                    ) : (
                                        <Button cargando={procesando} onClick={() => cambiarEstado(elegido)}>
                                            Activar
                                        </Button>
                                    ))}
                            </>
                        }
                    >
                        <DetalleLista
                            items={[
                                { etiqueta: 'Correo', valor: elegido.email },
                                { etiqueta: 'Rol', valor: elegido.rol_etiqueta ?? 'Sin rol' },
                                { etiqueta: 'Última actualización', valor: formatearFechaHora(elegido.actualizado) },
                            ]}
                        />
                        <ConfirmDialog
                            abierto={confirmando}
                            onCambiar={setConfirmando}
                            titulo={`¿Desactivar a «${elegido.name}»?`}
                            descripcion="Perderá el acceso de inmediato, incluso si tiene una sesión abierta. Su historial se conserva y puedes volver a activarlo."
                            confirmar="Desactivar"
                            peligro
                            cargando={procesando}
                            onConfirmar={() => cambiarEstado(elegido)}
                        />
                    </Drawer>
                )
            }
        />
    );
}
