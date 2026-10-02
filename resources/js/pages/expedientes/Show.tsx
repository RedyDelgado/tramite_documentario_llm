import { ArrowDownload16Regular, Attach16Regular, Mail20Regular } from '@fluentui/react-icons';
import { DetalleLista } from '@/components/data/DetalleLista';
import { AccionesAtencion } from '@/components/domain/AccionesAtencion';
import { AccionesRegistro } from '@/components/domain/AccionesRegistro';
import { EstadoBadge } from '@/components/domain/EstadoBadge';
import { LineaTiempo } from '@/components/domain/LineaTiempo';
import { SemaforoBadge } from '@/components/domain/SemaforoBadge';
import { AppShell } from '@/components/layouts/AppShell';
import { PageHeader } from '@/components/layouts/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { Card } from '@/components/ui/Card';
import { EmptyState } from '@/components/ui/EmptyState';
import { formatearFecha, formatearFechaHora } from '@/lib/fechas';
import { formatearBytes } from '@/lib/formato';
import type { CorreoDetalle, DocumentoDetalle, EventoHistorial, ExpedienteDetalle, Opcion, OpcionesDerivacion } from '@/types';

const ORIGEN = { correo: 'Correo', fisico: 'Documento físico', pdf: 'PDF subido' };

function EnlaceDocumento({ d }: { d: DocumentoDetalle }) {
    return (
        <a
            href={`/documentos/${d.id}/descargar`}
            className="inline-flex items-center gap-1 rounded-control text-base text-primary-600 hover:text-primary-500 hover:underline"
        >
            <Attach16Regular aria-hidden />
            {d.nombre}
        </a>
    );
}

function Correo({ c, documentos }: { c: CorreoDetalle; documentos: DocumentoDetalle[] }) {
    const adjuntos = documentos.filter((d) => c.documentos.includes(d.id));

    return (
        <article className="border-b border-border px-4 py-4 last:border-b-0">
            <header className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p className="text-base font-semibold text-fg">
                        {c.de_nombre ?? c.de_email}
                        {c.de_nombre && <span className="font-normal text-fg-muted"> &lt;{c.de_email}&gt;</span>}
                    </p>
                    <p className="text-sm text-fg-muted">Para: {c.para.join(', ') || '—'}</p>
                </div>
                <div className="flex items-center gap-2">
                    {c.es_reenvio && <Badge>Reenvío</Badge>}
                    <time dateTime={c.fecha} className="text-sm text-fg-muted">
                        {formatearFechaHora(c.fecha)}
                    </time>
                </div>
            </header>
            <p className="mt-2 text-base font-semibold text-fg">{c.asunto}</p>
            {c.cuerpo && <div className="mt-2 rounded-control bg-surface-subtle p-3 text-base whitespace-pre-wrap text-fg">{c.cuerpo}</div>}
            <footer className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1">
                {adjuntos.map((d) => (
                    <EnlaceDocumento key={d.id} d={d} />
                ))}
                <a href={`/correos/${c.id}/eml`} className="ml-auto inline-flex items-center gap-1 text-sm text-fg-muted hover:text-fg hover:underline">
                    <ArrowDownload16Regular aria-hidden />
                    Correo original (.eml)
                </a>
            </footer>
        </article>
    );
}

// En rojo sin haber vencido: el área no tiene quien lo atienda (8).
const motivoRojo = (e: ExpedienteDetalle) =>
    e.semaforo === 'rojo' && (!e.fecha_limite || e.fecha_limite >= new Date().toLocaleDateString('en-CA', { timeZone: 'America/Lima' })) ? 'Sin responsable' : undefined;

type Props = {
    expediente: ExpedienteDetalle;
    historial: EventoHistorial[];
    opcionesEmisor?: Opcion<number>[];
    opcionesTipoDocumento?: Opcion<number>[];
    derivacion?: OpcionesDerivacion;
};

export default function ExpedienteShow({ expediente: e, historial, opcionesEmisor, opcionesTipoDocumento, derivacion }: Props) {
    return (
        <AppShell>
            <PageHeader
                titulo={e.numero_registro ? `Expediente ${e.numero_registro}` : 'Expediente sin número'}
                descripcion={e.asunto}
                acciones={
                    <>
                        <AccionesAtencion expediente={e} derivacion={derivacion} />
                        <AccionesRegistro
                            expediente={e}
                            opciones={opcionesEmisor && opcionesTipoDocumento && { emisor: opcionesEmisor, tipoDocumento: opcionesTipoDocumento }}
                        />
                    </>
                }
            />
            <div className="grid items-start gap-4 lg:grid-cols-3">
                <Card titulo={`Correos (${e.correos.length})`} sinRelleno className="lg:col-span-2">
                    {e.correos.length === 0 ? (
                        <EmptyState icono={<Mail20Regular />} titulo="Sin correos" />
                    ) : (
                        e.correos.map((c) => <Correo key={c.id} c={c} documentos={e.documentos} />)
                    )}
                </Card>

                <div className="flex flex-col gap-4">
                    <Card titulo="Datos">
                        <DetalleLista
                            items={[
                                { etiqueta: 'Estado', valor: <EstadoBadge estado={e.estado} /> },
                                ...(e.semaforo ? [{ etiqueta: 'Semáforo', valor: <SemaforoBadge estado={e.semaforo} texto={motivoRojo(e)} /> }] : []),
                                ...(e.cierre_solicitado_at ? [{ etiqueta: 'Cierre', valor: <Badge tono="aviso">Pendiente de aprobación</Badge> }] : []),
                                { etiqueta: 'Código', valor: e.codigo ?? 'Se asigna al registrar como trámite' },
                                ...(e.motivo_anulacion ? [{ etiqueta: 'Motivo de anulación', valor: e.motivo_anulacion }] : []),
                                {
                                    etiqueta: 'Remitente',
                                    valor: (
                                        <span className="flex flex-wrap items-center gap-1">
                                            {e.remitente_nombre ? `${e.remitente_nombre} <${e.remitente_email}>` : e.remitente_email}
                                            {e.remitente_por_confirmar && <Badge>Por confirmar</Badge>}
                                        </span>
                                    ),
                                },
                                { etiqueta: 'Ingreso', valor: formatearFechaHora(e.fecha_ingreso) },
                                ...(e.registrado_at ? [{ etiqueta: 'Registrado', valor: formatearFechaHora(e.registrado_at) }] : []),
                                { etiqueta: 'Origen', valor: ORIGEN[e.origen] },
                                { etiqueta: 'Área', valor: e.area ?? 'Sin asignar' },
                                { etiqueta: 'Responsable', valor: e.responsable ?? (e.area ? 'Quien coordina el área' : 'Sin asignar') },
                                { etiqueta: 'Tipo de trámite', valor: e.tipo_tramite ?? 'Sin clasificar' },
                                {
                                    etiqueta: 'Fecha límite',
                                    valor: e.fecha_limite
                                        ? `${formatearFecha(e.fecha_limite)}${e.plazo_dias_aplicado ? ` (plazo de ${e.plazo_dias_aplicado} días)` : ''}`
                                        : 'Sin plazo',
                                },
                                { etiqueta: 'Requiere respuesta', valor: e.requiere_respuesta ? 'Sí' : 'No, para conocimiento' },
                                ...(e.atendido_at ? [{ etiqueta: 'Atendido', valor: formatearFechaHora(e.atendido_at) }] : []),
                                { etiqueta: 'Emisor', valor: e.emisor ?? '—' },
                                { etiqueta: 'Tipo de documento', valor: e.tipo_documento ?? '—' },
                            ]}
                        />
                    </Card>

                    <Card titulo={`Documentos (${e.documentos.length})`}>
                        {e.documentos.length === 0 ? (
                            <p className="text-base text-fg-muted">Sin documentos adjuntos.</p>
                        ) : (
                            <ul className="flex flex-col gap-3">
                                {e.documentos.map((d) => (
                                    <li key={d.id}>
                                        <EnlaceDocumento d={d} />
                                        <p className="text-sm text-fg-muted">
                                            {formatearBytes(d.tamano)} · {d.con_texto ? 'texto buscable' : 'sin texto extraído'} ·{' '}
                                            <span title={`SHA-256 ${d.sha256}`}>SHA-256 {d.sha256.slice(0, 12)}…</span>
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>

                    <Card titulo="Historial">
                        <LineaTiempo eventos={historial} />
                    </Card>
                </div>
            </div>
        </AppShell>
    );
}
