import { IcoAgregar16, IcoCandado, IcoCerrar16, IcoComentario, IcoCorrecto, IcoDerivar, IcoIa, IcoTomar } from '@/components/ui/iconos';
import { router, useForm, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/forms/FormField';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { ComboboxMultiple } from '@/components/ui/ComboboxMultiple';
import { Dialog } from '@/components/ui/Dialog';
import { IaPensando } from '@/components/ui/IaPensando';
import { IconButton } from '@/components/ui/IconButton';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Textarea } from '@/components/ui/Textarea';
import { BotonConfirmado } from '@/components/ui/BotonConfirmado';
import type { ExpedienteDetalle, OpcionesDerivacion } from '@/types';

type Props = { expediente: ExpedienteDetalle; derivacion?: OpcionesDerivacion };

type Abierto = 'derivar' | 'atender' | 'comentar' | 'cierre' | 'resolver' | null;

type FilaArea = { area_id: string; responsable_id: string; fecha_limite: string };

// Área, responsable, fecha y quitar en una fila: varias áreas caben sin desplazarse.
const FILA = 'grid items-center gap-2 sm:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_9.5rem_2rem]';

/** Acciones de atención (7, 8) según los permisos que calcula ExpedientePolicy; la regla final la aplica AtencionService. */
export function AccionesAtencion({ expediente: e, derivacion }: Props) {
    const [abierto, setAbierto] = useState<Abierto>(null);
    const [tomando, setTomando] = useState(false);
    const url = (accion: string) => `/expedientes/${e.id}/${accion}`;
    const cerrar = () => setAbierto(null);

    const sugerencia = derivacion?.sugerencia;
    const ia = derivacion?.ia;
    // Lo atendido por un área ya no se cambia ni se quita al reasignar.
    const atendidas = new Set(e.atenciones.filter((a) => a.estado.valor === 'atendido').map((a) => String(a.area_id)));
    // Primero lo ya decidido, luego la regla (determinista) y al final la IA (10: la IA propone, las reglas deciden).
    const derivar = useForm({
        tipo_tramite_id: String(e.tipo_tramite_id ?? sugerencia?.tipo_tramite_id ?? ia?.tipo_tramite_id ?? ''),
        areas: (e.atenciones.length
            ? e.atenciones.map((a) => ({ area_id: String(a.area_id), responsable_id: String(a.responsable_id ?? ''), fecha_limite: '' }))
            : [{ area_id: String(sugerencia?.area_id ?? ia?.area_id ?? ''), responsable_id: String(sugerencia?.responsable_id ?? ''), fecha_limite: '' }]) as FilaArea[],
        requiere_respuesta: e.requiere_respuesta,
        instruccion: '',
        nota: '',
        areas_copia: e.areas_copia.map((a) => a.id),
        toda_la_serie: false,
    });
    const errores = derivar.errors as Record<string, string | undefined>;
    const elegidas = derivar.data.areas.map((a) => a.area_id);
    const cambiarFila = (i: number, cambio: Partial<FilaArea>) =>
        derivar.setData(
            'areas',
            derivar.data.areas.map((a, j) => (j === i ? { ...a, ...cambio } : a)),
        );

    // Las indicaciones con la IA local: sin texto las propone desde el documento; con texto, las aclara.
    const [errorIa, setErrorIa] = useState<string | null>(null);
    const redaccion = useHttp<{ texto: string | null; instruccion: string | null; areas: number[] }, { texto: string }>('post', url('indicaciones-ia'), {
        texto: null,
        instruccion: null,
        areas: [],
    });
    const pedirIndicaciones = () => {
        setErrorIa(null);
        redaccion.transform(() => ({
            texto: derivar.data.nota.trim() || null,
            instruccion: derivar.data.instruccion || null,
            areas: derivar.data.areas.map((a) => Number(a.area_id)).filter(Boolean),
        }));
        redaccion.post(url('indicaciones-ia'), {
            onSuccess: (r) => derivar.setData('nota', r.texto),
            onError: (err) => setErrorIa(Object.values(err)[0] ?? 'No se pudo obtener el texto.'),
        }).catch(() => setErrorIa((actual) => actual ?? 'El modelo de IA no responde. Inténtalo en unos minutos.'));
    };

    const nota = useForm({ nota: '', aprobar: true });
    const soloConocimiento = !e.requiere_respuesta && !e.fecha_limite;

    const enviarNota = (accion: string, aprobar?: boolean) => {
        nota.transform((d) => ({ nota: d.nota || null, ...(aprobar === undefined ? {} : { aprobar }) }));
        nota.post(url(accion), {
            preserveScroll: true,
            onSuccess: () => {
                cerrar();
                nota.reset();
            },
        });
    };

    const pieNota = (accion: string, texto: string) => (
        <>
            <Button onClick={cerrar}>Cancelar</Button>
            <Button variante="primario" cargando={nota.processing} onClick={() => enviarNota(accion)}>
                {texto}
            </Button>
        </>
    );

    const campoNota = (etiqueta: string, requerido = false, ayuda?: string) => (
        <FormField etiqueta={etiqueta} requerido={requerido} ayuda={ayuda} error={nota.errors.nota}>
            {(c) => <Textarea {...c} value={nota.data.nota} maxLength={2000} onChange={(ev) => nota.setData('nota', ev.target.value)} />}
        </FormField>
    );

    return (
        <>
            {e.permisos.derivar && (
                <Button variante={e.estado.valor === 'registrado' ? 'primario' : 'secundario'} icono={<IcoDerivar />} onClick={() => setAbierto('derivar')}>
                    {e.estado.valor === 'registrado' ? 'Derivar' : 'Reasignar'}
                </Button>
            )}
            {e.permisos.tomar && (
                <BotonConfirmado
                    variante="primario"
                    icono={<IcoTomar />}
                    titulo={soloConocimiento ? '¿Tomar conocimiento?' : '¿Tomar en atención?'}
                    descripcion={
                        soloConocimiento
                            ? 'Queda registrado que tu área lo leyó y queda atendido por ella.'
                            : 'Tu área pasa a atenderlo; queda en el historial y corre el seguimiento.'
                    }
                    confirmar={soloConocimiento ? 'Tomar conocimiento' : 'Tomar en atención'}
                    cargando={tomando}
                    onConfirmar={(cerrar) =>
                        router.post(url('tomar'), {}, { preserveScroll: true, onStart: () => setTomando(true), onFinish: () => (setTomando(false), cerrar()) })
                    }
                >
                    {soloConocimiento ? 'Tomar conocimiento' : 'Tomar en atención'}
                </BotonConfirmado>
            )}
            {e.permisos.atender && !e.permisos.tomar && (
                <Button variante="primario" icono={<IcoCorrecto />} onClick={() => setAbierto('atender')}>
                    Marcar atendido
                </Button>
            )}
            {e.permisos.solicitar_cierre && (
                <Button icono={<IcoCandado />} onClick={() => setAbierto('cierre')}>
                    Solicitar cierre
                </Button>
            )}
            {e.permisos.resolver_cierre && (
                <Button variante="primario" icono={<IcoCorrecto />} onClick={() => setAbierto('resolver')}>
                    Resolver cierre
                </Button>
            )}
            {e.permisos.comentar && (
                <Button icono={<IcoComentario />} onClick={() => setAbierto('comentar')}>
                    Comentar
                </Button>
            )}

            {derivacion && (
                <Dialog
                    abierto={abierto === 'derivar'}
                    onCambiar={(v) => !v && cerrar()}
                    tamano="xl"
                    ocupado={redaccion.processing && <IaPensando>{derivar.data.nota.trim() ? 'Aclarando las indicaciones' : 'Redactando las indicaciones'}</IaPensando>}
                    titulo={e.estado.valor === 'registrado' ? 'Derivar' : 'Reasignar'}
                    descripcion={
                        [
                            sugerencia && `Sugerencia de la regla «${sugerencia.regla}».`,
                            ia && `Propuesta de la IA${ia.alta ? ' (alta confianza)' : ''}: ${[ia.confianza_area && `área ${Math.round(ia.confianza_area * 100)} %`, ia.confianza_tipo && `tipo ${Math.round(ia.confianza_tipo * 100)} %`].filter(Boolean).join(', ')}.`,
                            'Cada área lo atiende con su plazo, que sale del tipo y del área salvo que el documento fije una fecha; queda atendido cuando lo atiendan todas.',
                        ]
                            .filter(Boolean)
                            .join(' ')
                    }
                    pie={
                        <>
                            <Button onClick={cerrar}>Cancelar</Button>
                            <Button
                                variante="primario"
                                cargando={derivar.processing}
                                onClick={() => {
                                    derivar.transform((d) => ({
                                        ...d,
                                        tipo_tramite_id: Number(d.tipo_tramite_id) || null,
                                        areas: d.areas.map((a) => ({
                                            area_id: Number(a.area_id) || null,
                                            responsable_id: a.responsable_id ? Number(a.responsable_id) : null,
                                            fecha_limite: a.fecha_limite || null,
                                        })),
                                        instruccion: d.instruccion || null,
                                        nota: d.nota || null,
                                        // Un área que lo atiende no va además en copia.
                                        areas_copia: d.areas_copia.filter((id) => !d.areas.some((a) => Number(a.area_id) === id)),
                                    }));
                                    derivar.post(url('derivar'), { preserveScroll: true, onSuccess: () => cerrar() });
                                }}
                            >
                                {e.estado.valor === 'registrado' ? 'Derivar' : 'Reasignar'}
                            </Button>
                        </>
                    }
                >
                    <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
                        <div className="flex flex-col gap-4">
                            <FormField etiqueta="Tipo de trámite" requerido error={derivar.errors.tipo_tramite_id}>
                                {(c) => (
                                    <Select
                                        {...c}
                                        vacia="Elige el tipo"
                                        opciones={derivacion.tipos}
                                        value={derivar.data.tipo_tramite_id}
                                        onChange={(ev) => derivar.setData('tipo_tramite_id', ev.target.value)}
                                    />
                                )}
                            </FormField>
                            <FormField etiqueta="Instrucción" error={derivar.errors.instruccion}>
                                {(c) => (
                                    <Select
                                        {...c}
                                        vacia="Sin instrucción"
                                        opciones={derivacion.instrucciones.map((i) => ({ value: i.label, label: i.label }))}
                                        value={derivar.data.instruccion}
                                        onChange={(ev) => derivar.setData('instruccion', ev.target.value)}
                                    />
                                )}
                            </FormField>
                            <FormField
                                etiqueta="Indicaciones para quien lo atiende"
                                ayuda="Qué debe hacer, con detalle. Van en el correo que reciben las áreas."
                                error={derivar.errors.nota ?? errorIa ?? undefined}
                            >
                                {(c) => <Textarea {...c} rows={5} value={derivar.data.nota} maxLength={2000} onChange={(ev) => derivar.setData('nota', ev.target.value)} />}
                            </FormField>
                            {derivacion.redactar_con_ia && (
                                <div>
                                    <Button tamano="sm" icono={<IcoIa />} onClick={pedirIndicaciones}>
                                        {derivar.data.nota.trim() ? 'Mejorar con IA' : 'Redactar con IA'}
                                    </Button>
                                </div>
                            )}
                            <Checkbox
                                etiqueta="Requiere respuesta"
                                checked={derivar.data.requiere_respuesta}
                                onChange={(ev) => derivar.setData('requiere_respuesta', ev.target.checked)}
                            />
                            {e.serie && e.serie.expedientes.length > 1 && (
                                <Checkbox
                                    etiqueta={`Derivar toda la serie «${e.serie.nombre}» (${e.serie.expedientes.length} documentos)`}
                                    checked={derivar.data.toda_la_serie}
                                    onChange={(ev) => derivar.setData('toda_la_serie', ev.target.checked)}
                                />
                            )}
                        </div>

                        <div className="flex flex-col gap-5">
                            <fieldset className="flex flex-col gap-2">
                                <legend className="text-base font-medium text-fg">
                                    Áreas que lo atienden<span className="text-danger" aria-hidden> *</span>
                                </legend>
                                <p className="text-sm text-fg-muted">La primera es la principal. Sin responsable, lo atienden el titular y los suplentes del área. Fecha solo si el documento la fija.</p>
                                <div className={`${FILA} max-sm:hidden text-sm text-fg-muted`} aria-hidden>
                                    <span>Área</span>
                                    <span>Responsable</span>
                                    <span>Fecha límite</span>
                                </div>
                                {derivar.data.areas.map((fila, i) =>
                                    atendidas.has(fila.area_id) ? (
                                        <div key={fila.area_id} className={FILA}>
                                            <span className="truncate text-base text-fg">{derivacion.areas.find((a) => String(a.value) === fila.area_id)?.label}</span>
                                            <Badge tono="ok">Ya lo atendió</Badge>
                                        </div>
                                    ) : (
                                        <div key={i} className="flex flex-col gap-1">
                                            <div className={FILA}>
                                                <Select
                                                    aria-label={`Área ${i + 1}`}
                                                    aria-invalid={errores[`areas.${i}.area_id`] ? true : undefined}
                                                    vacia="Elige el área"
                                                    opciones={derivacion.areas.filter((a) => String(a.value) === fila.area_id || !elegidas.includes(String(a.value)))}
                                                    value={fila.area_id}
                                                    // Otra área, otro responsable: vuelve a quien la coordina.
                                                    onChange={(ev) => cambiarFila(i, { area_id: ev.target.value, responsable_id: '' })}
                                                />
                                                <Select
                                                    aria-label={`Responsable del área ${i + 1}`}
                                                    vacia={derivacion.titulares[Number(fila.area_id)] ? `${derivacion.titulares[Number(fila.area_id)]} (titular)` : 'Quien coordina'}
                                                    opciones={derivacion.usuarios}
                                                    value={fila.responsable_id}
                                                    onChange={(ev) => cambiarFila(i, { responsable_id: ev.target.value })}
                                                />
                                                <Input
                                                    aria-label={`Fecha límite del área ${i + 1}`}
                                                    aria-invalid={errores[`areas.${i}.fecha_limite`] ? true : undefined}
                                                    type="date"
                                                    value={fila.fecha_limite}
                                                    onChange={(ev) => cambiarFila(i, { fecha_limite: ev.target.value })}
                                                />
                                                {derivar.data.areas.length > 1 && (
                                                    <IconButton
                                                        icono={<IcoCerrar16 />}
                                                        etiqueta="Quitar esta área"
                                                        tamano="sm"
                                                        onClick={() =>
                                                            derivar.setData(
                                                                'areas',
                                                                derivar.data.areas.filter((_, j) => j !== i),
                                                            )
                                                        }
                                                    />
                                                )}
                                            </div>
                                            {[errores[`areas.${i}.area_id`], errores[`areas.${i}.responsable_id`], errores[`areas.${i}.fecha_limite`]]
                                                .filter(Boolean)
                                                .map((m) => (
                                                    <p key={m} className="text-sm text-danger">
                                                        {m}
                                                    </p>
                                                ))}
                                        </div>
                                    ),
                                )}
                                {errores.areas && <p className="text-sm text-danger">{errores.areas}</p>}
                                {derivar.data.areas.length < 10 && (
                                    <div>
                                        <Button
                                            tamano="sm"
                                            icono={<IcoAgregar16 />}
                                            onClick={() => derivar.setData('areas', [...derivar.data.areas, { area_id: '', responsable_id: '', fecha_limite: '' }])}
                                        >
                                            Agregar área
                                        </Button>
                                    </div>
                                )}
                            </fieldset>

                            <FormField etiqueta="En copia" ayuda="Opcional: estas áreas lo ven para conocimiento, sin atenderlo; también reciben el correo." error={errores.areas_copia}>
                                {(c) => (
                                    <ComboboxMultiple
                                        {...c}
                                        placeholder="Escribe para buscar un área"
                                        opciones={derivacion.areas.filter((a) => !elegidas.includes(String(a.value)))}
                                        valor={derivar.data.areas_copia}
                                        onCambiar={(v) => derivar.setData('areas_copia', v)}
                                    />
                                )}
                            </FormField>
                        </div>
                    </div>
                </Dialog>
            )}

            <Dialog
                abierto={abierto === 'atender'}
                onCambiar={(v) => !v && cerrar()}
                titulo="Marcar atendido"
                descripcion="Deja constancia de cómo lo atendió tu área. El expediente queda atendido cuando lo atiendan todas sus áreas."
                pie={pieNota('atender', 'Marcar atendido')}
            >
                {campoNota('Cómo se atendió', true, 'Por ejemplo: «Se respondió con el Oficio N° 045-2026, entregado en mesa de partes».')}
            </Dialog>

            <Dialog abierto={abierto === 'comentar'} onCambiar={(v) => !v && cerrar()} titulo="Comentar" descripcion="Queda en el historial y cuenta como movimiento." pie={pieNota('comentar', 'Comentar')}>
                {campoNota('Comentario', true)}
            </Dialog>

            <Dialog
                abierto={abierto === 'cierre'}
                onCambiar={(v) => !v && cerrar()}
                titulo="Solicitar cierre"
                descripcion="Si el tipo de trámite exige aprobación, queda pendiente hasta que la den; si no, se cierra ahora."
                pie={pieNota('solicitar-cierre', 'Solicitar cierre')}
            >
                {campoNota('Cómo se atendió')}
            </Dialog>

            <Dialog
                abierto={abierto === 'resolver'}
                onCambiar={(v) => !v && cerrar()}
                titulo="Resolver el cierre"
                descripcion="Al aprobar, el expediente queda cerrado y atendido. Para rechazar, indica el motivo."
                pie={
                    <>
                        <Button onClick={cerrar}>Cancelar</Button>
                        <Button variante="peligro" cargando={nota.processing} onClick={() => enviarNota('resolver-cierre', false)}>
                            Rechazar
                        </Button>
                        <Button variante="primario" cargando={nota.processing} onClick={() => enviarNota('resolver-cierre', true)}>
                            Aprobar cierre
                        </Button>
                    </>
                }
            >
                {campoNota('Motivo u observación')}
            </Dialog>
        </>
    );
}
