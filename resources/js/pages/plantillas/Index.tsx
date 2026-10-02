import { Add20Regular, DocumentText20Regular } from '@fluentui/react-icons';
import { Link, router } from '@inertiajs/react';
import type { Columna } from '@/components/data/DataTable';
import { ActivoBadge } from '@/components/domain/ActivoBadge';
import { ListPage } from '@/components/layouts/ListPage';
import { botonClases } from '@/components/ui/Button';
import { EmptyState } from '@/components/ui/EmptyState';
import { formatearFechaHora } from '@/lib/fechas';
import type { Paginado, Plantilla } from '@/types';
import type { ComponentProps } from 'react';
import { cerrarModal, rutaModal } from '@/lib/modal';
import Formulario from './Form';

const columnas: Columna<Plantilla>[] = [
    { clave: 'nombre', titulo: 'Plantilla', celda: (p) => <span className="font-semibold">{p.nombre}</span> },
    { clave: 'tipo', titulo: 'Tipo de documento', celda: (p) => p.tipo },
    { clave: 'actualizado', titulo: 'Última actualización', celda: (p) => formatearFechaHora(p.actualizado) },
    { clave: 'estado', titulo: 'Estado', ancho: '8rem', celda: (p) => <ActivoBadge activo={p.activa} femenino /> },
];

export default function PlantillasIndex({ plantillas, formulario }: { plantillas: Paginado<Plantilla> } & { formulario?: Omit<ComponentProps<typeof Formulario>, 'onCerrar'> }) {
    return (
        <>
            {formulario && <Formulario {...formulario} onCerrar={() => cerrarModal('/plantillas')} />}
            <ListPage
                titulo="Plantillas de documentos"
                descripcion="Modelos de oficios, cartas e informes que emite la institución. Se llenan con los datos del expediente al redactar."
                acciones={
                    <Link href={rutaModal('/plantillas/create')} preserveScroll className={botonClases({ variante: 'primario' })}>
                        <Add20Regular />
                        Nueva plantilla
                    </Link>
                }
                tabla={{
                    titulo: 'Plantillas de documentos',
                    columnas,
                    filas: plantillas.data,
                    claveFila: (p) => p.id,
                    onElegirFila: (p) => router.visit(rutaModal(`/plantillas/${p.id}/edit`), { preserveScroll: true }),
                    vacio: (
                        <EmptyState
                            icono={<DocumentText20Regular />}
                            titulo="Aún no hay plantillas"
                            descripcion="Por ejemplo: respuesta a requerimiento de información, oficio de invitación."
                        />
                    ),
                }}
                paginacion={plantillas}
            />
        </>
    );
}
