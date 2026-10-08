import { IcoAdjunto16, IcoAmenaza16, IcoCopiar, IcoCorreo, IcoCorreo16, IcoDescargar16, IcoEnviar, IcoIa, IcoImagen } from '@/components/ui/iconos';
import { Link, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import { DetalleLista } from '@/components/data/DetalleLista';
import { AccionesAtencion } from '@/components/domain/AccionesAtencion';
import { AccionesRegistro } from '@/components/domain/AccionesRegistro';
import { EstadoBadge } from '@/components/domain/EstadoBadge';
import { LineaTiempo } from '@/components/domain/LineaTiempo';
import { OriginalPapel } from '@/components/domain/OriginalPapel';
import { SerieCard } from '@/components/domain/SerieCard';
import { SemaforoBadge } from '@/components/domain/SemaforoBadge';
import { Badge } from '@/components/ui/Badge';
import { Button, botonClases } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Dialog } from '@/components/ui/Dialog';
import { EmptyState } from '@/components/ui/EmptyState';
import { Spinner } from '@/components/ui/Spinner';
import { Textarea } from '@/components/ui/Textarea';
import { formatearFecha, formatearFechaHora } from '@/lib/fechas';
import { formatearBytes } from '@/lib/formato';
import type { CorreoDetalle, DocumentoDetalle, EventoHistorial, ExpedienteDetalle, Opcion, OpcionesAgrupacion, OpcionesDerivacion } from '@/types';

const ORIGEN = { correo: 'Correo', fisico: 'Documento físico', pdf: 'PDF subido' };

function EnlaceDocumento({ d }: { d: DocumentoDetalle }) {
    // En cuarentena (11): se ve que llegó, pero no se ofrece abrirlo.
    if (d.amenaza) {
        return (
            <span className="inline-flex flex-wrap items-center gap-1 text-base text-fg">
                <IcoAdjunto16 aria-hidden />
                {d.nombre}
                <Badge tono="peligro" icono={<IcoAmenaza16 aria-hidden />}>
                    En cuarentena: {d.amenaza}
                </Badge>
            </span>
        );
    }

    return (
        <a
            href={`/documentos/${d.id}/descargar`}
            className="inline-flex items-center gap-1 rounded-control text-base text-primary-600 hover:text-primary-500 hover:underline"
        >
            <IcoAdjunto16 aria-hidden />
            {d.nombre}
        </a>
    );
}

/** Texto de respuesta sugerido por la IA local (fase 6): se revisa y se pega en el Word; nada se guarda ni se envía desde aquí. */
function SugerenciaIa({ expedienteId }: { expedienteId: number }) {
    const [abierto, setAbierto] = useState(false);
    const [texto, setTexto] = useState('');
    const [copiado, setCopiado] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const ruta = `/expedientes/${expedienteId}/borrador-ia`;
    const pedido = useHttp<Record<string, never>, { texto: string }>('post', ruta, {});
    const pedir = () => {
        setAbierto(true);
        setTexto('');
        setError(null);
        // El servidor responde 422 con un mensaje cuando el modelo no está o no contesta.
        pedido
            .post(ruta, { onSuccess: (r) => setTexto(r.texto), onError: (e) => setError(Object.values(e)[0] ?? 'No se pudo obtener la sugerencia.') })
            .catch(() => setError((actual) => actual ?? 'El modelo de IA no responde. Inténtalo en unos minutos.'));
    };
    const copiar = () => navigator.clipboard.writeText(texto).then(() => setCopiado(true));

    return (
        <>
            <Button icono={<IcoIa />} onClick={pedir}>
                Sugerir respuesta
            </Button>
            <Dialog
                abierto={abierto}
                onCambiar={(a) => (setAbierto(a), setCopiado(false))}
                titulo="Respuesta sugerida por la IA"
                descripcion="Es un punto de partida: revísala, complétala donde dice [COMPLETAR] y pégala en tu Word. Nada se guarda ni se envía desde aquí."
                tamano="lg"
                pie={
                    <>
                        <Button onClick={() => setAbierto(false)}>Cerrar</Button>
                        <Button variante="primario" icono={<IcoCopiar />} disabled={!texto} onClick={copiar}>
                            {copiado ? 'Copiado' : 'Copiar texto'}
                        </Button>
                    </>
                }
            >
                {pedido.processing ? (
                    <p className="flex items-center gap-2 py-6 text-base text-fg-muted">
                        <Spinner /> Redactando con el modelo local; puede tardar hasta un minuto…
                    </p>
                ) : error ? (
                    <p className="py-6 text-base text-danger">{error}</p>
                ) : (
                    <Textarea aria-label="Texto sugerido" rows={16} value={texto} onChange={(e) => setTexto(e.target.value)} />
                )}
            </Dialog>
        </>
    );
}

/** El correo con su diseño, aislado en un iframe sin scripts; las imágenes externas, solo si se piden (como en Gmail). */
function CorreoOriginal({ c }: { c: CorreoDetalle }) {
    const [abierto, setAbierto] = useState(false);
    const [imagenes, setImagenes] = useState(false);

    return (
        <>
            <button type="button" onClick={() => setAbierto(true)} className="inline-flex items-center gap-1 text-sm text-fg-muted hover:text-fg hover:underline">
                <IcoCorreo16 />
                Ver como llegó
            </button>
            <Dialog
                abierto={abierto}
                onCambiar={(a) => (setAbierto(a), setImagenes(false))}
                titulo={c.asunto || '(sin asunto)'}
                descripcion={imagenes ? undefined : 'Las imágenes externas están ocultas: al mostrarlas, el remitente puede saber que abriste el correo.'}
                acciones={
                    !imagenes && (
                        <Button icono={<IcoImagen />} onClick={() => setImagenes(true)}>
                            Mostrar imágenes
                        </Button>
                    )
                }
                tamano="xl"
            >
                <iframe
                    title={`Correo: ${c.asunto}`}
                    src={`/correos/${c.id}/vista${imagenes ? '?imagenes=1' : ''}`}
                    sandbox="allow-popups allow-popups-to-escape-sandbox"
                    className="h-[70vh] w-full rounded-card bg-surface"
                />
            </Dialog>
        </>
    );
}

function Correo({ c, documentos }: { c: CorreoDetalle; documentos: DocumentoDetalle[] }) {
    const adjuntos = documentos.filter((d) => c.documentos.includes(d.id));

    return (
        <article className="border-b border-separador px-5 py-4 last:border-b-0">
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
            {c.cuerpo && <div className="mt-2 rounded-card bg-surface-subtle px-4 py-3 text-base whitespace-pre-wrap text-fg">{c.cuerpo}</div>}
            <footer className="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1">
                {adjuntos.map((d) => (
                    <EnlaceDocumento key={d.id} d={d} />
                ))}
                {adjuntos.some((d) => d.amenaza) ? (
                    <span className="ml-auto text-sm text-fg-muted">Correo original en cuarentena</span>
                ) : (
                    <span className="ml-auto flex items-center gap-4">
                        <CorreoOriginal c={c} />
                        <a href={`/correos/${c.id}/eml`} className="inline-flex items-center gap-1 text-sm text-fg-muted hover:text-fg hover:underline">
                            <IcoDescargar16 aria-hidden />
                            Correo original (.eml)
                        </a>
                    </span>
                )}
            </footer>
        </article>
    );
}

// En rojo sin haber vencido: el área no tiene quien lo atienda (8).
const motivoRojo = (e: ExpedienteDetalle) =>
    e.semaforo === 'rojo' && (!e.fecha_limite || e.fecha_limite >= new Date().toLocaleDateString('en-CA', { timeZone: 'America/Lima' })) ? 'Sin responsable' : undefined;

export type DetalleExpedienteProps = {
    expediente: ExpedienteDetalle;
    historial: EventoHistorial[];
    opcionesEmisor?: Opcion<number>[];
    opcionesTipoDocumento?: Opcion<number>[];
    derivacion?: OpcionesDerivacion;
    custodia?: { ubicaciones: Opcion<number>[]; usuarios: Opcion<number>[] };
    agrupacion?: OpcionesAgrupacion;
};

export default function ExpedienteShow({ expediente: e, historial, opcionesEmisor, opcionesTipoDocumento, derivacion, custodia, agrupacion, onCerrar }: DetalleExpedienteProps & { onCerrar: () => void }) {
    return (
        <Dialog
            abierto
            onCambiar={(abierto) => !abierto && onCerrar()}
            tamano="xl"
            titulo={e.numero_registro ? `Expediente ${e.numero_registro}` : 'Expediente sin número'}
            descripcion={e.asunto}
            acciones={
                    <>
                        <AccionesAtencion expediente={e} derivacion={derivacion} />
                        {e.permisos.redactar && (
                            <Link href={`/salientes/create?expediente=${e.id}`} className={botonClases()}>
                                <IcoEnviar />
                                Responder con documento
                            </Link>
                        )}
                        {e.permisos.sugerir && <SugerenciaIa expedienteId={e.id} />}
                        <AccionesRegistro
                            expediente={e}
                            opciones={opcionesEmisor && opcionesTipoDocumento && { emisor: opcionesEmisor, tipoDocumento: opcionesTipoDocumento }}
                        />
                    </>
                }
        >
            <div className="grid items-start gap-4 lg:grid-cols-3">
                <Card titulo={`Correos (${e.correos.length})`} sinRelleno className="lg:col-span-2">
                    {e.correos.length === 0 ? (
                        <EmptyState icono={<IcoCorreo />} titulo="Sin correos" />
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
                                            {[e.remitente_nombre, e.remitente_email && (e.remitente_nombre ? `<${e.remitente_email}>` : e.remitente_email)].filter(Boolean).join(' ')}
                                            {e.remitente_por_confirmar && <Badge>Por confirmar</Badge>}
                                        </span>
                                    ),
                                },
                                { etiqueta: 'Ingreso', valor: formatearFechaHora(e.fecha_ingreso) },
                                ...(e.registrado_at ? [{ etiqueta: 'Registrado', valor: formatearFechaHora(e.registrado_at) }] : []),
                                { etiqueta: 'Origen', valor: ORIGEN[e.origen] },
                                { etiqueta: 'Área', valor: e.area ?? 'Sin asignar' },
                                { etiqueta: 'Responsable', valor: e.responsable ?? (e.area ? 'Quien coordina el área' : 'Sin asignar') },
                                ...(e.areas_copia.length ? [{ etiqueta: 'En copia', valor: e.areas_copia.map((a) => a.nombre).join(', ') }] : []),
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
                                ...(e.institucion ? [{ etiqueta: 'Institución', valor: e.institucion }] : []),
                                { etiqueta: 'Tipo de documento', valor: e.tipo_documento ?? '—' },
                                ...(e.numero_documento ? [{ etiqueta: 'N° de documento', valor: e.numero_documento }] : []),
                                ...(e.fecha_documento ? [{ etiqueta: 'Fecha del documento', valor: formatearFecha(e.fecha_documento) }] : []),
                                ...(e.folios ? [{ etiqueta: 'Folios', valor: e.motivo_folios ? `${e.folios} (${e.motivo_folios})` : e.folios }] : []),
                            ]}
                        />
                    </Card>

                    <SerieCard expediente={e} agrupacion={agrupacion} />

                    <OriginalPapel expediente={e} custodia={custodia} />

                    <Card titulo={`${e.origen === 'fisico' ? 'Copia digital' : 'Documentos'} (${e.documentos.length})`}>
                        {e.documentos.length === 0 ? (
                            <p className="text-base text-fg-muted">Sin documentos adjuntos.</p>
                        ) : (
                            <ul className="flex flex-col gap-3">
                                {e.documentos.map((d) => (
                                    <li key={d.id}>
                                        <EnlaceDocumento d={d} />
                                        <p className="text-sm text-fg-muted">
                                            {formatearBytes(d.tamano)} · {d.amenaza ? 'no se procesa' : d.con_texto ? 'texto buscable' : 'sin texto extraído'} ·{' '}
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
        </Dialog>
    );
}
