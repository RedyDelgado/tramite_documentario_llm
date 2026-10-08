import { IcoAlerta, IcoCorrecto, IcoCorreo, IcoEnviar, IcoExpedientes, IcoTablero } from '@/components/ui/iconos';
import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { GraficoMensual } from '@/components/data/GraficoMensual';
import { SemaforoBadge, SIGNIFICADO_SEMAFORO, type Semaforo } from '@/components/domain/SemaforoBadge';
import { AppShell } from '@/components/layouts/AppShell';
import { PageHeader } from '@/components/layouts/PageHeader';
import { Card } from '@/components/ui/Card';
import { EmptyState } from '@/components/ui/EmptyState';
import { Tooltip } from '@/components/ui/Tooltip';
import { cn } from '@/lib/cn';

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
// En el inicio solo lo principal de cada lista; el resto, en Expedientes.
const FILAS = 5;

// Saludo según la hora de Lima, con el nombre de pila sin títulos («Dr.», «Lic.»).
function saludo(nombre: string): string {
    const hora = Number(new Date().toLocaleString('en-US', { hour: 'numeric', hourCycle: 'h23', timeZone: 'America/Lima' }));
    const pila = nombre.split(/\s+/).find((p) => p !== '' && !p.endsWith('.')) ?? nombre;

    return `${hora < 12 ? 'Buenos días' : hora < 19 ? 'Buenas tardes' : 'Buenas noches'}, ${pila}`;
}

const hoy = () => new Date().toLocaleDateString('es-PE', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'America/Lima' });

function Cifra({ titulo, valor, detalle, icono, href, destacar }: { titulo: string; valor: string | number; detalle: string; icono: ReactNode; href?: string; destacar?: boolean }) {
    const contenido = (
        <div className="flex items-start gap-3">
            <span className={cn('flex size-9 shrink-0 items-center justify-center rounded-full', destacar ? 'bg-primary-600 text-on-primary' : 'bg-primary-50 text-primary-600')}>{icono}</span>
            <div className="min-w-0">
                <p className="truncate text-sm font-medium text-fg-muted">{titulo}</p>
                <p className="font-titulos text-lg font-bold tracking-tight text-fg tabular-nums">{valor}</p>
                <p className="truncate text-sm text-fg-muted" title={detalle}>
                    {detalle}
                </p>
            </div>
        </div>
    );

    return (
        <section className="rounded-card bg-surface px-4 py-3 shadow-card">
            {href ? (
                <Link href={href} className="block rounded-control transition-opacity hover:opacity-80">
                    {contenido}
                </Link>
            ) : (
                contenido
            )}
        </section>
    );
}

/** Lista con barra de magnitud de un solo tono y el valor escrito: no depende del color. */
function Ranking({ filas, vacio }: { filas: { clave: string; etiqueta: ReactNode; detalle?: string; valor: number; texto?: string; href?: string }[]; vacio: string }) {
    if (filas.length === 0) return <p className="py-2 text-sm text-fg-muted">{vacio}</p>;
    const max = Math.max(1, ...filas.map((f) => f.valor));

    return (
        <ul className="flex flex-col gap-2.5">
            {filas.map((f) => {
                const fila = (
                    <>
                        <span className="flex items-baseline justify-between gap-2 text-sm">
                            <span className="min-w-0 truncate text-fg">
                                {f.etiqueta}
                                {f.detalle && <span className="text-fg-muted"> · {f.detalle}</span>}
                            </span>
                            <span className="shrink-0 font-semibold text-fg tabular-nums">{f.texto ?? f.valor}</span>
                        </span>
                        <span aria-hidden className="mt-1 block h-1.5 rounded-full bg-relleno">
                            <span className="block h-full rounded-full bg-primary-600" style={{ width: `${Math.max(2, (f.valor / max) * 100)}%` }} />
                        </span>
                    </>
                );

                return (
                    <li key={f.clave}>
                        {f.href ? (
                            <Link href={f.href} className="block rounded-control hover:opacity-80">
                                {fila}
                            </Link>
                        ) : (
                            fila
                        )}
                    </li>
                );
            })}
        </ul>
    );
}

export default function Inicio({ indicadores: i }: { indicadores: Indicadores | null }) {
    const usuario = usePage().props.auth.user;
    const titulo = usuario ? saludo(usuario.name) : 'Inicio';

    if (!i) {
        return (
            <AppShell>
                <PageHeader titulo={titulo} descripcion={hoy()} />
                <Card>
                    <EmptyState
                        icono={<IcoTablero />}
                        titulo="Sin indicadores de trámites"
                        descripcion="Tu rol administra la configuración; el contenido de los trámites lo ven director, mesa de partes y coordinadores."
                    />
                </Card>
            </AppShell>
        );
    }

    const porcentaje = (parte: number, total: number) => (total ? `${Math.round((parte / total) * 100)} %` : '—');
    const dias = (t: Tiempo) => `${t.dias.toLocaleString('es-PE')} d`;

    return (
        <AppShell>
            <PageHeader titulo={titulo} descripcion={`${hoy()} · lo que puedes ver de los trámites`} />
            <div className="flex flex-col gap-4">
                <div className="grid grid-cols-[repeat(auto-fit,minmax(11rem,1fr))] gap-3">
                    <Cifra
                        titulo="Por revisar"
                        valor={i.por_revisar}
                        detalle="Correos que esperan registro"
                        icono={<IcoCorreo />}
                        href="/expedientes?vista=por_revisar"
                        destacar={i.por_revisar > 0}
                    />
                    <Cifra titulo="Abiertos" valor={i.abiertos} detalle="Derivados o en atención" icono={<IcoExpedientes />} />
                    <Cifra titulo="Sin movimiento" valor={i.sin_movimiento} detalle={`Más de ${i.dias_sin_movimiento} días quietos`} icono={<IcoAlerta />} />
                    <Cifra
                        titulo="Atendidos en plazo"
                        valor={porcentaje(i.en_plazo.en_plazo, i.en_plazo.atendidos)}
                        detalle={`${i.en_plazo.en_plazo} de ${i.en_plazo.atendidos} en el año`}
                        icono={<IcoCorrecto />}
                    />
                    <Cifra
                        titulo="Respondidos desde el sistema"
                        valor={porcentaje(i.adopcion.desde_sistema, i.adopcion.con_respuesta)}
                        detalle={`${i.adopcion.desde_sistema} de ${i.adopcion.con_respuesta} en el año`}
                        icono={<IcoEnviar />}
                    />
                </div>

                <div className="grid items-stretch gap-4 lg:grid-cols-[1fr_1fr_2fr]">
                    <Card titulo="Semáforos">
                        <ul className="flex flex-col gap-2">
                            {ORDEN_SEMAFORO.map((s) => (
                                <li key={s}>
                                    <Tooltip texto={SIGNIFICADO_SEMAFORO[s]}>
                                        <Link href={`/expedientes?semaforo=${s}`} className="flex items-center justify-between gap-2 rounded-control hover:opacity-80">
                                            <SemaforoBadge estado={s} />
                                            <span className="text-base font-semibold text-fg tabular-nums">{i.por_semaforo[s]}</span>
                                        </Link>
                                    </Tooltip>
                                </li>
                            ))}
                        </ul>
                    </Card>
                    <Card titulo="Por estado">
                        <Ranking
                            filas={i.por_estado.slice(0, 6).map((e) => ({ clave: e.valor, etiqueta: e.etiqueta, valor: e.total, href: `/expedientes?estado=${e.valor}` }))}
                            vacio="Aún no hay expedientes."
                        />
                    </Card>
                    <Card titulo="Ingresos y atenciones por mes">
                        {i.tendencia.some((m) => m.ingresados || m.atendidos) ? (
                            <GraficoMensual
                                titulo="Ingresos y atenciones por mes, últimos 12 meses"
                                alto="compacto"
                                filas={i.tendencia}
                                series={[
                                    { clave: 'ingresados', nombre: 'Ingresados' },
                                    { clave: 'atendidos', nombre: 'Atendidos' },
                                ]}
                            />
                        ) : (
                            <p className="py-2 text-sm text-fg-muted">Aún no hay trámites registrados.</p>
                        )}
                    </Card>
                </div>

                <div className="grid items-stretch gap-4 lg:grid-cols-3">
                    <Card titulo="Carga por responsable">
                        <Ranking
                            filas={i.carga.slice(0, FILAS).map((c) => ({
                                clave: `${c.area}-${c.responsable ?? ''}`,
                                etiqueta: c.responsable ?? 'Quien coordina',
                                detalle: c.area,
                                valor: c.abiertos,
                                texto: c.rojos ? `${c.abiertos} · ${c.rojos} en rojo` : String(c.abiertos),
                            }))}
                            vacio="No hay expedientes abiertos."
                        />
                    </Card>
                    <Card titulo="Días de atención por área">
                        <Ranking
                            filas={i.tiempo_por_area.slice(0, FILAS).map((t) => ({ clave: t.nombre, etiqueta: t.nombre, detalle: `${t.total} atendidos`, valor: t.dias, texto: dias(t) }))}
                            vacio="Sin atendidos este año."
                        />
                    </Card>
                    <Card titulo="Días de atención por tipo">
                        <Ranking
                            filas={i.tiempo_por_tipo.slice(0, FILAS).map((t) => ({ clave: t.nombre, etiqueta: t.nombre, detalle: `${t.total} atendidos`, valor: t.dias, texto: dias(t) }))}
                            vacio="Sin atendidos este año."
                        />
                    </Card>
                </div>
            </div>
        </AppShell>
    );
}
