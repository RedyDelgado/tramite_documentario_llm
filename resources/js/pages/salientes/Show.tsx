import { IcoCorrecto, IcoDescargar, IcoEditar, IcoEnviar, IcoSubirDocumento } from '@/components/ui/iconos';
import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { DetalleLista } from '@/components/data/DetalleLista';
import { FormField } from '@/components/forms/FormField';
import { SemaforoBadge } from '@/components/domain/SemaforoBadge';
import { Badge } from '@/components/ui/Badge';
import { Button, botonClases } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Dialog } from '@/components/ui/Dialog';
import { Textarea } from '@/components/ui/Textarea';
import { BotonConfirmado } from '@/components/ui/BotonConfirmado';
import { formatearFecha, formatearFechaHora } from '@/lib/fechas';
import type { SalienteDetalle } from '@/types';

const ESTADO_ENVIO: Record<string, { tono: 'neutro' | 'ok' | 'peligro' | 'aviso'; texto: string }> = {
    pendiente: { tono: 'neutro', texto: 'Pendiente' },
    enviado: { tono: 'ok', texto: 'Enviado' },
    rebotado: { tono: 'peligro', texto: 'Rebotado' },
    fallido: { tono: 'peligro', texto: 'Falló' },
};

export type DetalleSalienteProps = { saliente: SalienteDetalle };

export default function SalienteShow({ saliente: s, onCerrar }: DetalleSalienteProps & { onCerrar: () => void }) {
    const [devolviendo, setDevolviendo] = useState(false);
    const [procesando, setProcesando] = useState(false);
    const devolucion = useForm({ observacion: '' });
    const [firmado, setFirmado] = useState<File | null>(null);
    const subirFirmado = (archivo: File) =>
        router.post(
            `/salientes/${s.id}/firmado`,
            { archivo },
            { forceFormData: true, preserveScroll: true, onStart: () => setProcesando(true), onFinish: () => (setProcesando(false), setFirmado(null)) },
        );
    const accion = (ruta: string, cerrar: () => void) =>
        router.post(`/salientes/${s.id}/${ruta}`, {}, { preserveScroll: true, onStart: () => setProcesando(true), onFinish: () => (setProcesando(false), cerrar()) });

    return (
        <Dialog
            abierto
            onCambiar={(abierto) => !abierto && onCerrar()}
            tamano="xl"
            titulo={s.numero ?? 'Borrador'}
            descripcion={s.asunto}
            acciones={
                    <>
                        {s.permisos.editar && (
                            <Link href={`/salientes/${s.id}/edit`} className={botonClases()}>
                                <IcoEditar />
                                Editar
                            </Link>
                        )}
                        {s.permisos.revision && (
                            <BotonConfirmado
                                variante="primario"
                                icono={<IcoEnviar />}
                                titulo="¿Enviar a revisión?"
                                descripcion="Ya no se podrá editar salvo que quien revisa lo devuelva."
                                confirmar="Enviar a revisión"
                                cargando={procesando}
                                onConfirmar={(cerrar) => accion('revision', cerrar)}
                            >
                                Enviar a revisión
                            </BotonConfirmado>
                        )}
                        {s.permisos.aprobar && (
                            <>
                                <Button onClick={() => setDevolviendo(true)}>Devolver</Button>
                                <BotonConfirmado
                                    variante="primario"
                                    icono={<IcoCorrecto />}
                                    titulo="¿Aprobar y numerar?"
                                    descripcion="Recibe su número; quien lo redactó lo pone en el Word y sube el documento final, que se envía. No se puede deshacer."
                                    confirmar="Aprobar"
                                    cargando={procesando}
                                    onConfirmar={(cerrar) => accion('aprobar', cerrar)}
                                >
                                    Aprobar y numerar
                                </BotonConfirmado>
                            </>
                        )}
                        {s.permisos.firmar && !s.final && (
                            <label className={botonClases({ variante: 'primario' })}>
                                <IcoSubirDocumento />
                                Subir documento final
                                <input
                                    type="file"
                                    accept=".doc,.docx,.pdf,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                                    className="sr-only"
                                    onChange={(e) => e.target.files?.[0] && setFirmado(e.target.files[0])}
                                />
                            </label>
                        )}
                        {s.final && (
                            <a href={`/salientes/${s.id}/descargar/final`} className={botonClases()}>
                                <IcoDescargar />
                                Documento final
                            </a>
                        )}
                        {s.borrador && (
                            <a href={`/salientes/${s.id}/descargar/borrador`} className={botonClases()}>
                                <IcoDescargar />
                                Borrador
                            </a>
                        )}
                    </>
                }
        >
            <div className="grid items-start gap-4 lg:grid-cols-3">
                <Card titulo="Documento" className="lg:col-span-2">
                    <div className="flex flex-col gap-3 text-base">
                        {s.observacion && <p className="text-danger">Observación de la revisión: {s.observacion}</p>}
                        {s.estado.valor === 'aprobado' && !s.final && (
                            <p className="rounded-card bg-primary-50 px-4 py-3 text-fg">
                                Número asignado: <strong>{s.numero}</strong>. Ponlo en el Word, fírmalo si corresponde y súbelo con «Subir documento final»: ese archivo es el que se envía.
                            </p>
                        )}
                        <DetalleLista
                            items={[
                                { etiqueta: 'Borrador revisado', valor: s.borrador ?? '—' },
                                ...(s.final ? [{ etiqueta: 'Documento final', valor: s.final }] : []),
                                ...(s.sha256_final ? [{ etiqueta: 'Huella del final', valor: <span title={s.sha256_final}>SHA-256 {s.sha256_final.slice(0, 12)}…</span> }] : []),
                            ]}
                        />
                        {s.mensaje && (
                            <div>
                                <p className="mb-1 text-sm font-medium text-fg-muted">Mensaje del correo</p>
                                <p className="whitespace-pre-line">{s.mensaje}</p>
                            </div>
                        )}
                    </div>
                </Card>
                <div className="flex flex-col gap-4">
                    <Card titulo="Datos">
                        <DetalleLista
                            items={[
                                { etiqueta: 'Estado', valor: <Badge tono={s.estado.valor === 'enviado' ? 'ok' : 'neutro'}>{s.estado.etiqueta}</Badge> },
                                ...(s.estado.valor === 'aprobado' && !s.final ? [{ etiqueta: 'Envío', valor: 'Espera el documento final' }] : []),
                                { etiqueta: 'Tipo', valor: s.tipo },
                                { etiqueta: 'Área que emite', valor: s.area },
                                ...(s.expediente
                                    ? [{ etiqueta: s.es_respuesta ? 'Responde a' : 'Expediente', valor: <Link href={`/expedientes/${s.expediente.id}`} className="text-primary-700 hover:underline">{s.expediente.numero_registro ?? 'Expediente'}</Link> }]
                                    : []),
                                { etiqueta: 'Subió', valor: s.autor },
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

            <ConfirmDialog
                abierto={firmado !== null}
                onCambiar={(abierto) => !abierto && setFirmado(null)}
                titulo="¿Subir el documento final y enviarlo?"
                descripcion={`«${firmado?.name ?? ''}» debe llevar el número ${s.numero ?? ''}: será la versión final y se enviará de inmediato a sus destinatarios.`}
                confirmar="Subir y enviar"
                cargando={procesando}
                onConfirmar={() => firmado && subirFirmado(firmado)}
            />

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
        </Dialog>
    );
}
