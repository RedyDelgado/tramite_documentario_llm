import { ArrowDownload20Regular, CheckmarkCircle20Regular, DocumentArrowUp20Regular, Edit20Regular, Send20Regular } from '@fluentui/react-icons';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { DetalleLista } from '@/components/data/DetalleLista';
import { FormField } from '@/components/forms/FormField';
import { SemaforoBadge } from '@/components/domain/SemaforoBadge';
import { AppShell } from '@/components/layouts/AppShell';
import { PageHeader } from '@/components/layouts/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { Button, botonClases } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Dialog } from '@/components/ui/Dialog';
import { Textarea } from '@/components/ui/Textarea';
import { formatearFecha, formatearFechaHora } from '@/lib/fechas';
import type { SalienteDetalle } from '@/types';

const ESTADO_ENVIO: Record<string, { tono: 'neutro' | 'ok' | 'peligro' | 'aviso'; texto: string }> = {
    pendiente: { tono: 'neutro', texto: 'Pendiente' },
    enviado: { tono: 'ok', texto: 'Enviado' },
    rebotado: { tono: 'peligro', texto: 'Rebotado' },
    fallido: { tono: 'peligro', texto: 'Falló' },
};

export default function SalienteShow({ saliente: s }: { saliente: SalienteDetalle }) {
    const [devolviendo, setDevolviendo] = useState(false);
    const [procesando, setProcesando] = useState(false);
    const devolucion = useForm({ observacion: '' });
    const accion = (ruta: string) =>
        router.post(`/salientes/${s.id}/${ruta}`, {}, { preserveScroll: true, onStart: () => setProcesando(true), onFinish: () => setProcesando(false) });

    return (
        <AppShell>
            <PageHeader
                titulo={s.numero ?? 'Borrador'}
                descripcion={s.asunto}
                acciones={
                    <>
                        {s.permisos.editar && (
                            <Link href={`/salientes/${s.id}/edit`} className={botonClases()}>
                                <Edit20Regular />
                                Editar
                            </Link>
                        )}
                        {s.permisos.revision && (
                            <Button variante="primario" icono={<Send20Regular />} cargando={procesando} onClick={() => accion('revision')}>
                                Enviar a revisión
                            </Button>
                        )}
                        {s.permisos.aprobar && (
                            <>
                                <Button onClick={() => setDevolviendo(true)}>Devolver</Button>
                                <Button variante="primario" icono={<CheckmarkCircle20Regular />} cargando={procesando} onClick={() => accion('aprobar')}>
                                    Aprobar y numerar
                                </Button>
                            </>
                        )}
                        {s.permisos.firmar && (
                            <label className={botonClases({ variante: s.esperar_firma && !s.firmado ? 'primario' : 'secundario' })}>
                                <DocumentArrowUp20Regular />
                                {s.firmado ? 'Reemplazar firmado' : 'Adjuntar PDF firmado'}
                                <input
                                    type="file"
                                    accept="application/pdf"
                                    className="sr-only"
                                    onChange={(e) =>
                                        e.target.files?.[0] &&
                                        router.post(`/salientes/${s.id}/firmado`, { archivo: e.target.files[0] }, { forceFormData: true, preserveScroll: true })
                                    }
                                />
                            </label>
                        )}
                        {s.firmado && (
                            <a href={`/salientes/${s.id}/descargar/firmado`} className={botonClases()}>
                                <ArrowDownload20Regular />
                                Firmado
                            </a>
                        )}
                        <a href={`/salientes/${s.id}/descargar/pdf`} className={botonClases()}>
                            <ArrowDownload20Regular />
                            PDF
                        </a>
                        <a href={`/salientes/${s.id}/descargar/docx`} className={botonClases()}>
                            <ArrowDownload20Regular />
                            Word
                        </a>
                    </>
                }
            />
            <div className="grid items-start gap-4 lg:grid-cols-3">
                <Card titulo="Texto" className="lg:col-span-2">
                    {s.observacion && <p className="mb-3 text-base text-danger">Observación de la revisión: {s.observacion}</p>}
                    <p className="text-base whitespace-pre-line">{s.cuerpo}</p>
                </Card>
                <div className="flex flex-col gap-4">
                    <Card titulo="Datos">
                        <DetalleLista
                            items={[
                                { etiqueta: 'Estado', valor: <Badge tono={s.estado.valor === 'enviado' ? 'ok' : 'neutro'}>{s.estado.etiqueta}</Badge> },
                                ...(s.estado.valor === 'aprobado' && s.esperar_firma && !s.firmado ? [{ etiqueta: 'Envío', valor: 'Espera el PDF firmado' }] : []),
                                { etiqueta: 'Tipo', valor: s.tipo },
                                { etiqueta: 'Área que emite', valor: s.area },
                                ...(s.expediente
                                    ? [{ etiqueta: s.es_respuesta ? 'Responde a' : 'Expediente', valor: <Link href={`/expedientes/${s.expediente.id}`} className="text-primary-700 hover:underline">{s.expediente.numero_registro ?? 'Expediente'}</Link> }]
                                    : []),
                                { etiqueta: 'Redactó', valor: s.autor },
                                ...(s.aprobador ? [{ etiqueta: 'Aprobó', valor: `${s.aprobador} · ${formatearFechaHora(s.aprobado_at)}` }] : []),
                                ...(s.requiere_respuesta
                                    ? [{
                                          etiqueta: 'Respuesta',
                                          valor: s.respondido_at ? (
                                              `Respondido ${formatearFechaHora(s.respondido_at)}`
                                          ) : s.fecha_limite_respuesta ? (
                                              <span className="flex flex-wrap items-center gap-2">
                                                  hasta el {formatearFecha(s.fecha_limite_respuesta)} {s.semaforo && <SemaforoBadge estado={s.semaforo} />}
                                              </span>
                                          ) : (
                                              `Plazo de ${s.plazo_respuesta_dias} días hábiles desde el envío`
                                          ),
                                      }]
                                    : []),
                                ...(s.sha256_pdf ? [{ etiqueta: 'PDF aprobado', valor: <span title={s.sha256_pdf}>SHA-256 {s.sha256_pdf.slice(0, 12)}…</span> }] : []),
                            ]}
                        />
                    </Card>
                    <Card titulo={`Destinatarios (${s.destinatarios.length})`}>
                        <ul className="flex flex-col gap-2">
                            {s.destinatarios.map((d) => {
                                const envio = s.envios.find((e) => e.email === d.email);
                                const estado = envio ? ESTADO_ENVIO[envio.estado] : null;
                                return (
                                    <li key={d.email} className="flex flex-wrap items-center justify-between gap-2 text-base">
                                        <span>
                                            {d.nombre ? `${d.nombre} <${d.email}>` : d.email}
                                            {envio?.detalle && <span className="block text-sm text-fg-muted">{envio.detalle}</span>}
                                        </span>
                                        {estado && <Badge tono={estado.tono}>{estado.texto}</Badge>}
                                    </li>
                                );
                            })}
                        </ul>
                    </Card>
                </div>
            </div>

            <Dialog
                abierto={devolviendo}
                onCambiar={setDevolviendo}
                titulo="Devolver al autor"
                descripcion="Vuelve a borrador con tu observación; no recibe número."
                pie={
                    <>
                        <Button onClick={() => setDevolviendo(false)}>Cancelar</Button>
                        <Button
                            variante="primario"
                            cargando={devolucion.processing}
                            onClick={() => devolucion.post(`/salientes/${s.id}/devolver`, { preserveScroll: true, onSuccess: () => setDevolviendo(false) })}
                        >
                            Devolver
                        </Button>
                    </>
                }
            >
                <FormField etiqueta="Observación" requerido error={devolucion.errors.observacion}>
                    {(c) => <Textarea {...c} value={devolucion.data.observacion} maxLength={2000} onChange={(e) => devolucion.setData('observacion', e.target.value)} />}
                </FormField>
            </Dialog>
        </AppShell>
    );
}
