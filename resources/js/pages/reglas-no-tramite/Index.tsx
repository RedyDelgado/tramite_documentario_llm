import { Add20Regular, MailProhibited20Regular, Search20Regular } from '@fluentui/react-icons';
import { Link, router } from '@inertiajs/react';
import type { Columna } from '@/components/data/DataTable';
import { ActivoBadge } from '@/components/domain/ActivoBadge';
import { ListPage } from '@/components/layouts/ListPage';
import { botonClases } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { useFiltros } from '@/hooks/useFiltros';
import type { Opcion, Paginado, ReglaNoTramite } from '@/types';
import type { ComponentProps } from 'react';
import { cerrarModal, rutaModal } from '@/lib/modal';
import Formulario from './Form';

type Filtros = { q?: string; campo?: string; estado?: string };

const columnas: Columna<ReglaNoTramite>[] = [
    { clave: 'nombre', titulo: 'Regla', celda: (r) => <span className="font-semibold">{r.nombre}</span> },
    {
        clave: 'condicion',
        titulo: 'Condición',
        celda: (r) => (
            <span>
                {r.campo_etiqueta}: <code>{r.valor}</code>
            </span>
        ),
    },
    { clave: 'estado', titulo: 'Estado', ancho: '8rem', celda: (r) => <ActivoBadge activo={r.activa} femenino /> },
];

type Props = { reglas: Paginado<ReglaNoTramite>; filtros: Filtros; opcionesCampo: Opcion<string>[] };

export default function ReglasNoTramiteIndex({ reglas, filtros: iniciales, opcionesCampo, formulario }: Props & { formulario?: Omit<ComponentProps<typeof Formulario>, 'onCerrar'> }) {
    const { filtros, cambiar, cargando } = useFiltros<Filtros>(iniciales);
    const hayFiltros = Boolean(filtros.q || filtros.campo || filtros.estado);

    return (
        <>
            {formulario && <Formulario {...formulario} onCerrar={() => cerrarModal('/reglas-no-tramite')} />}
            <ListPage
                titulo="Correo no trámite"
                descripcion="El correo que cumple una regla activa entra archivado como no trámite: sin número ni semáforo, recuperable. Aplica a los correos que lleguen desde ahora."
                acciones={
                    <Link href={rutaModal('/reglas-no-tramite/create')} preserveScroll className={botonClases({ variante: 'primario' })}>
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
                            aria-label="Campo"
                            className="w-48"
                            vacia="Todos los campos"
                            opciones={opcionesCampo}
                            value={filtros.campo ?? ''}
                            onChange={(e) => cambiar({ campo: e.target.value })}
                        />
                        <Input
                            type="search"
                            aria-label="Buscar"
                            placeholder="Buscar por nombre o valor"
                            iconoInicio={<Search20Regular />}
                            className="w-64"
                            value={filtros.q ?? ''}
                            onChange={(e) => cambiar({ q: e.target.value }, { diferido: true })}
                        />
                    </>
                }
                tabla={{
                    titulo: 'Reglas de correo no trámite',
                    columnas,
                    filas: reglas.data,
                    claveFila: (r) => r.id,
                    onElegirFila: (r) => router.visit(rutaModal(`/reglas-no-tramite/${r.id}/edit`), { preserveScroll: true }),
                    cargando,
                    vacio: (
                        <EmptyState
                            icono={<MailProhibited20Regular />}
                            titulo={hayFiltros ? 'No hay reglas que coincidan con los filtros' : 'Aún no hay reglas'}
                            descripcion={hayFiltros ? 'Cambia la búsqueda, el campo o el estado.' : 'Por ejemplo: remitentes noreply o boletines con enlace de baja.'}
                        />
                    ),
                }}
                paginacion={reglas}
            />
        </>
    );
}
