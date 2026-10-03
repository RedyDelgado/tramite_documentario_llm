import { Board20Regular } from '@fluentui/react-icons';
import { Link } from '@inertiajs/react';
import { DataTable } from '@/components/data/DataTable';
import { GraficoMensual } from '@/components/data/GraficoMensual';
import { SemaforoBadge, type Semaforo } from '@/components/domain/SemaforoBadge';
import { AppShell } from '@/components/layouts/AppShell';
import { PageHeader } from '@/components/layouts/PageHeader';
import { Card } from '@/components/ui/Card';
import { EmptyState } from '@/components/ui/EmptyState';

type Tiempo = { nombre: string; dias: number; total: number };
type Carga = { area: string; responsable: string | null; abiertos: number; rojos: number };

/** PanelService::indicadores. */
type Indicadores = {
    por_estado: { valor: string; etiqueta: string; total: number }[];
    por_semaforo: Record<Semaforo, number>;
    abiertos: number;
    por_revisar: number;
    sin_movimiento: number;
    dias_sin_movimiento: number;
    en_plazo: { atendidos: number; en_plazo: number };
    adopcion: { con_respuesta: number; desde_sistema: number };
    tendencia: { mes: string; ingresados: number; atendidos: number }[];
    tiempo_por_area: Tiempo[];
    tiempo_por_tipo: Tiempo[];
    carga: Carga[];
};

const ORDEN_SEMAFORO: Semaforo[] = ['rojo', 'amarillo', 'verde', 'gris'];

function Cifra({ titulo, valor, detalle, href }: { titulo: string; valor: string | number; detalle?: string; href?: string }) {
    const contenido = (
        <>
            <p className="text-sm text-fg-muted">{titulo}</p>
            <p className="text-xl font-semibold text-fg tabular-nums">{valor}</p>
            {detalle && <p className="text-sm text-fg-muted">{detalle}</p>}
        </>
    );

    return <Card>{href ? <Link href={href} className="block hover:underline">{contenido}</Link> : contenido}</Card>;
}

/** Barras horizontales de un solo tono: magnitud, con el valor escrito (no depende del color). */
function Barras({ filas, href }: { filas: { clave: string; etiqueta: string; total: number }[]; href: (clave: string) => string }) {
    const max = Math.max(1, ...filas.map((f) => f.total));

    return (
        <ul className="flex flex-col gap-2">
            {filas.map((f) => (
                <li key={f.clave}>
                    <Link href={href(f.clave)} className="grid grid-cols-[9rem_1fr_3rem] items-center gap-2 text-base text-fg hover:underline">
                        <span className="truncate">{f.etiqueta}</span>
                        <span className="h-2 rounded-r-full bg-primary-600" style={{ width: `${(f.total / max) * 100}%` }} aria-hidden />
                        <span className="text-right tabular-nums">{f.total}</span>
                    </Link>
                </li>
            ))}
        </ul>
    );
}

const columnasTiempo = [
    { clave: 'nombre', titulo: 'Nombre', celda: (t: Tiempo) => t.nombre },
    { clave: 'dias', titulo: 'Días promedio', alinear: 'derecha' as const, ancho: '8rem', celda: (t: Tiempo) => t.dias.toLocaleString('es-PE') },
    { clave: 'total', titulo: 'Atendidos', alinear: 'derecha' as const, ancho: '7rem', celda: (t: Tiempo) => t.total },
];

const columnasCarga = [
    { clave: 'area', titulo: 'Área', celda: (c: Carga) => c.area },
    { clave: 'responsable', titulo: 'Responsable', celda: (c: Carga) => c.responsable ?? <span className="text-fg-muted">Quien coordina el área</span> },
    { clave: 'abiertos', titulo: 'Abiertos', alinear: 'derecha' as const, ancho: '6rem', celda: (c: Carga) => c.abiertos },
    { clave: 'rojos', titulo: 'En rojo', alinear: 'derecha' as const, ancho: '6rem', celda: (c: Carga) => c.rojos },
];

export default function Inicio({ indicadores: i }: { indicadores: Indicadores | null }) {
    if (!i) {
        return (
            <AppShell>
                <PageHeader titulo="Inicio" />
                <Card>
                    <EmptyState
                        icono={<Board20Regular />}
                        titulo="Sin indicadores de trámites"
                        descripcion="Tu rol administra la configuración; el contenido de los trámites lo ven director, administrativo y coordinadores."
                    />
                </Card>
            </AppShell>
        );
    }

    const porcentaje = (parte: number, total: number) => (total ? `${Math.round((parte / total) * 100)} %` : '—');

    return (
        <AppShell>
            <PageHeader titulo="Inicio" descripcion="Expedientes que puedes ver: estado, semáforos, tiempos de atención y carga." />
            <div className="flex flex-col gap-4">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    <Cifra titulo="Abiertos" valor={i.abiertos} detalle="Derivados o en atención" />
                    <Cifra titulo="Por revisar" valor={i.por_revisar} detalle="Correos que esperan registro" href="/expedientes?estado=por_revisar" />
                    <Cifra titulo="Sin movimiento" valor={i.sin_movimiento} detalle={`Abiertos con más de ${i.dias_sin_movimiento} días quietos`} />
                    <Cifra
                        titulo="Atendidos en plazo"
                        valor={porcentaje(i.en_plazo.en_plazo, i.en_plazo.atendidos)}
                        detalle={`${i.en_plazo.en_plazo} de ${i.en_plazo.atendidos} con plazo, en el año`}
                    />
                    <Cifra
                        titulo="Respondidos desde el sistema"
                        valor={porcentaje(i.adopcion.desde_sistema, i.adopcion.con_respuesta)}
                        detalle={`${i.adopcion.desde_sistema} de ${i.adopcion.con_respuesta} que exigían respuesta, en el año`}
                    />
                </div>

                <div className="grid items-start gap-4 lg:grid-cols-2">
                    <Card titulo="Semáforos">
                        <ul className="flex flex-col gap-2">
                            {ORDEN_SEMAFORO.map((s) => (
                                <li key={s}>
                                    <Link href={`/expedientes?semaforo=${s}`} className="flex items-center justify-between gap-2 hover:underline">
                                        <SemaforoBadge estado={s} />
                                        <span className="text-lg font-semibold tabular-nums">{i.por_semaforo[s]}</span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Card>
                    <Card titulo="Por estado">
                        {i.por_estado.length === 0 ? (
                            <p className="text-base text-fg-muted">Aún no hay expedientes.</p>
                        ) : (
                            <Barras filas={i.por_estado.map((e) => ({ clave: e.valor, etiqueta: e.etiqueta, total: e.total }))} href={(v) => `/expedientes?estado=${v}`} />
                        )}
                    </Card>
                </div>

                <Card titulo="Ingresos y atenciones por mes">
                    {i.tendencia.some((m) => m.ingresados || m.atendidos) ? (
                        <GraficoMensual
                            titulo="Ingresos y atenciones por mes, últimos 12 meses"
                            filas={i.tendencia}
                            series={[
                                { clave: 'ingresados', nombre: 'Registrados' },
                                { clave: 'atendidos', nombre: 'Atendidos' },
                            ]}
                        />
                    ) : (
                        <EmptyState icono={<Board20Regular />} titulo="Aún no hay trámites registrados" />
                    )}
                </Card>

                <Card titulo="Carga por responsable" sinRelleno>
                    <DataTable
                        titulo="Carga por responsable"
                        columnas={columnasCarga}
                        filas={i.carga}
                        claveFila={(c) => `${c.area}-${c.responsable ?? ''}`}
                        vacio={<EmptyState icono={<Board20Regular />} titulo="No hay expedientes abiertos" />}
                    />
                </Card>

                <div className="grid items-start gap-4 lg:grid-cols-2">
                    <Card titulo="Tiempo de atención por área" sinRelleno>
                        <DataTable titulo="Tiempo de atención por área" columnas={columnasTiempo} filas={i.tiempo_por_area} claveFila={(t) => t.nombre} vacio={<EmptyState icono={<Board20Regular />} titulo="Sin atendidos este año" />} />
                    </Card>
                    <Card titulo="Tiempo de atención por tipo" sinRelleno>
                        <DataTable titulo="Tiempo de atención por tipo" columnas={columnasTiempo} filas={i.tiempo_por_tipo} claveFila={(t) => t.nombre} vacio={<EmptyState icono={<Board20Regular />} titulo="Sin atendidos este año" />} />
                    </Card>
                </div>
            </div>
        </AppShell>
    );
}
