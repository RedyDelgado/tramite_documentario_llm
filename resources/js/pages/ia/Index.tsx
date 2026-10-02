import { BrainCircuit20Regular } from '@fluentui/react-icons';
import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { DataTable } from '@/components/data/DataTable';
import { DetalleLista } from '@/components/data/DetalleLista';
import { Pagination } from '@/components/data/Pagination';
import { AppShell } from '@/components/layouts/AppShell';
import { PageHeader } from '@/components/layouts/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { Card } from '@/components/ui/Card';
import { EmptyState } from '@/components/ui/EmptyState';
import { BotonConfirmado } from '@/components/ui/BotonConfirmado';
import type { Paginado } from '@/types';

type Categoria = { nombre: string; total: number; aciertos: number };
type Version = { version: string; modo: string; total: number; aciertos_area: number; aciertos_tipo: number };
type Correccion = {
    id: number;
    expediente_id: number;
    numero_registro: string | null;
    campo: string;
    valor_ia: string | null;
    valor_humano: string | null;
    usuario: string | null;
    puede_resolver: boolean;
};

type Props = {
    precision: { area: Categoria[]; tipo: Categoria[]; versiones: Version[] };
    correcciones: Paginado<Correccion>;
    modo: 'sombra' | 'activo';
    modelo: { version: string | null; entrenado_en: string | null; ejemplos: number | null } | null;
};

const pct = (aciertos: number, total: number) => (total ? `${Math.round((aciertos / total) * 100)} %` : '—');

const columnasCategoria = [
    { clave: 'nombre', titulo: 'Categoría', celda: (c: Categoria) => c.nombre },
    { clave: 'total', titulo: 'Decididos', alinear: 'derecha' as const, ancho: '7rem', celda: (c: Categoria) => c.total },
    { clave: 'pct', titulo: 'Acierto', alinear: 'derecha' as const, ancho: '7rem', celda: (c: Categoria) => pct(c.aciertos, c.total) },
];

function Precision({ titulo, filas }: { titulo: string; filas: Categoria[] }) {
    const total = filas.reduce((s, f) => s + f.total, 0);
    const aciertos = filas.reduce((s, f) => s + f.aciertos, 0);

    return (
        <Card titulo={`${titulo} · ${pct(aciertos, total)} en ${total}`} sinRelleno>
            <DataTable
                titulo={titulo}
                columnas={columnasCategoria}
                filas={filas}
                claveFila={(f) => f.nombre}
                vacio={<EmptyState icono={<BrainCircuit20Regular />} titulo="Aún no hay decisiones con las que comparar" />}
            />
        </Card>
    );
}

export default function IaIndex({ precision, correcciones, modo, modelo }: Props) {
    const [resolviendo, setResolviendo] = useState<number | null>(null);
    const resolver = (id: number, validar: boolean, cerrar: () => void) =>
        router.post(`/ia/correcciones/${id}`, { validar }, { preserveScroll: true, onStart: () => setResolviendo(id), onFinish: () => (setResolviendo(null), cerrar()) });

    const columnasCorreccion = [
        {
            clave: 'expediente',
            titulo: 'Expediente',
            ancho: '9rem',
            celda: (c: Correccion) => (
                <Link href={`/expedientes/${c.expediente_id}`} className="text-primary-700 hover:underline">
                    {c.numero_registro ?? 'Sin número'}
                </Link>
            ),
        },
        { clave: 'campo', titulo: 'Campo', ancho: '9rem', celda: (c: Correccion) => c.campo },
        { clave: 'ia', titulo: 'Propuso la IA', celda: (c: Correccion) => c.valor_ia ?? '—' },
        { clave: 'humano', titulo: 'Decidió', celda: (c: Correccion) => `${c.valor_humano ?? '—'}${c.usuario ? ` (${c.usuario})` : ''}` },
        {
            clave: 'acciones',
            titulo: 'Validar',
            ancho: '13rem',
            celda: (c: Correccion) =>
                c.puede_resolver ? (
                    <span className="flex gap-2">
                        <BotonConfirmado
                            tamano="sm"
                            titulo="¿Validar la corrección?"
                            descripcion={`Servirá para reentrenar a la IA: ${c.campo.toLowerCase()} «${c.valor_humano ?? '—'}» en lugar de «${c.valor_ia ?? '—'}».`}
                            confirmar="Validar"
                            cargando={resolviendo === c.id}
                            onConfirmar={(cerrar) => resolver(c.id, true, cerrar)}
                        >
                            Validar
                        </BotonConfirmado>
                        <BotonConfirmado
                            tamano="sm"
                            titulo="¿Rechazar la corrección?"
                            descripcion="No servirá para reentrenar a la IA."
                            confirmar="Rechazar"
                            peligro
                            cargando={resolviendo === c.id}
                            onConfirmar={(cerrar) => resolver(c.id, false, cerrar)}
                        >
                            Rechazar
                        </BotonConfirmado>
                    </span>
                ) : (
                    <span className="text-sm text-fg-muted">La valida otra persona</span>
                ),
        },
    ];

    return (
        <AppShell>
            <PageHeader titulo="Inteligencia artificial" descripcion="Cuánto acierta la IA frente a las decisiones humanas y qué correcciones servirán para reentrenarla." />
            <div className="flex flex-col gap-4">
                <div className="grid items-start gap-4 lg:grid-cols-3">
                    <Card titulo="Estado">
                        <DetalleLista
                            items={[
                                { etiqueta: 'Modo', valor: <Badge tono={modo === 'activo' ? 'marca' : 'neutro'}>{modo === 'activo' ? 'Activo: propone al derivar' : 'Sombra: mide sin mostrar'}</Badge> },
                                {
                                    etiqueta: 'Modelo',
                                    valor: modelo === null ? 'Servicio de IA sin respuesta' : (modelo.version ?? 'Similitud con el catálogo (sin entrenar)'),
                                },
                                ...(modelo?.entrenado_en ? [{ etiqueta: 'Entrenado', valor: `${modelo.entrenado_en} · ${modelo.ejemplos ?? '?'} ejemplos` }] : []),
                            ]}
                        />
                    </Card>
                    <div className="lg:col-span-2">
                        <Precision titulo="Área" filas={precision.area} />
                    </div>
                </div>
                <Precision titulo="Tipo de trámite" filas={precision.tipo} />

                <Card titulo={`Correcciones por validar (${correcciones.meta.total})`} sinRelleno>
                    <DataTable
                        titulo="Correcciones por validar"
                        columnas={columnasCorreccion}
                        filas={correcciones.data}
                        claveFila={(c) => c.id}
                        vacio={<EmptyState icono={<BrainCircuit20Regular />} titulo="No hay correcciones pendientes" descripcion="Aparecen cuando una derivación contradice a la IA." />}
                    />
                    <Pagination links={correcciones.links} meta={correcciones.meta} />
                </Card>

                {precision.versiones.length > 0 && (
                    <Card titulo="Por versión del modelo" sinRelleno>
                        <DataTable
                            titulo="Por versión del modelo"
                            columnas={[
                                { clave: 'version', titulo: 'Versión', celda: (v: Version) => v.version },
                                { clave: 'modo', titulo: 'Modo', ancho: '7rem', celda: (v: Version) => v.modo },
                                { clave: 'total', titulo: 'Decididos', alinear: 'derecha' as const, ancho: '7rem', celda: (v: Version) => v.total },
                                { clave: 'area', titulo: 'Acierto área', alinear: 'derecha' as const, ancho: '8rem', celda: (v: Version) => pct(v.aciertos_area, v.total) },
                                { clave: 'tipo', titulo: 'Acierto tipo', alinear: 'derecha' as const, ancho: '8rem', celda: (v: Version) => pct(v.aciertos_tipo, v.total) },
                            ]}
                            filas={precision.versiones}
                            claveFila={(v) => `${v.version}-${v.modo}`}
                        />
                    </Card>
                )}
            </div>
        </AppShell>
    );
}
