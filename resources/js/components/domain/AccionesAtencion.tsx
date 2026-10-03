import { IcoCandado, IcoComentario, IcoCorrecto, IcoDerivar, IcoTomar } from '@/components/ui/iconos';
import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/forms/FormField';
import { Button } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Dialog } from '@/components/ui/Dialog';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Textarea } from '@/components/ui/Textarea';
import { BotonConfirmado } from '@/components/ui/BotonConfirmado';
import type { ExpedienteDetalle, OpcionesDerivacion } from '@/types';

type Props = { expediente: ExpedienteDetalle; derivacion?: OpcionesDerivacion };

type Abierto = 'derivar' | 'comentar' | 'cierre' | 'resolver' | null;

/** Acciones de atención (7, 8) según los permisos que calcula ExpedientePolicy; la regla final la aplica AtencionService. */
export function AccionesAtencion({ expediente: e, derivacion }: Props) {
    const [abierto, setAbierto] = useState<Abierto>(null);
    const [tomando, setTomando] = useState(false);
    const url = (accion: string) => `/expedientes/${e.id}/${accion}`;
    const cerrar = () => setAbierto(null);

    const sugerencia = derivacion?.sugerencia;
    const ia = derivacion?.ia;
    // Primero lo ya decidido, luego la regla (determinista) y al final la IA (10: la IA propone, las reglas deciden).
    const derivar = useForm({
        tipo_tramite_id: String(e.tipo_tramite_id ?? sugerencia?.tipo_tramite_id ?? ia?.tipo_tramite_id ?? ''),
        area_id: String(e.area_principal_id ?? sugerencia?.area_id ?? ia?.area_id ?? ''),
        responsable_id: String(e.responsable_id ?? (e.area_principal_id ? '' : (sugerencia?.responsable_id ?? ''))),
        requiere_respuesta: e.requiere_respuesta,
        instruccion: '',
        fecha_limite: '',
        nota: '',
        areas_copia: e.areas_copia.map((a) => a.id),
        toda_la_serie: false,
    });
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

    const campoNota = (etiqueta: string, requerido = false) => (
        <FormField etiqueta={etiqueta} requerido={requerido} error={nota.errors.nota}>
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
                            ? 'Queda registrado que lo leíste y el expediente pasa a atendido.'
                            : 'Pasas a atenderlo; queda en el historial y corre el seguimiento.'
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
                    titulo={e.estado.valor === 'registrado' ? 'Derivar' : 'Reasignar'}
                    descripcion={
                        [
                            sugerencia && `Sugerencia de la regla «${sugerencia.regla}».`,
                            ia && `Propuesta de la IA${ia.alta ? ' (alta confianza)' : ''}: ${[ia.confianza_area && `área ${Math.round(ia.confianza_area * 100)} %`, ia.confianza_tipo && `tipo ${Math.round(ia.confianza_tipo * 100)} %`].filter(Boolean).join(', ')}.`,
                            'Revisa antes de derivar: el plazo sale del tipo y del área, salvo que el documento fije una fecha.',
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
                                        area_id: Number(d.area_id) || null,
                                        responsable_id: d.responsable_id ? Number(d.responsable_id) : null,
                                        instruccion: d.instruccion || null,
                                        fecha_limite: d.fecha_limite || null,
                                        nota: d.nota || null,
                                        // Si el área elegida estaba en copia, deja de estarlo: ya es la responsable.
                                        areas_copia: d.areas_copia.filter((id) => id !== Number(d.area_id)),
                                    }));
                                    derivar.post(url('derivar'), { preserveScroll: true, onSuccess: () => cerrar() });
                                }}
                            >
                                {e.estado.valor === 'registrado' ? 'Derivar' : 'Reasignar'}
                            </Button>
                        </>
                    }
                >
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
                        <FormField etiqueta="Área" requerido error={derivar.errors.area_id}>
                            {(c) => (
                                <Select
                                    {...c}
                                    vacia="Elige el área"
                                    opciones={derivacion.areas}
                                    value={derivar.data.area_id}
                                    onChange={(ev) => derivar.setData('area_id', ev.target.value)}
                                />
                            )}
                        </FormField>
                        <FormField etiqueta="Responsable" ayuda="Opcional: sin él, lo atienden el titular y los suplentes del área." error={derivar.errors.responsable_id}>
                            {(c) => (
                                <Select
                                    {...c}
                                    vacia="Quien coordina el área"
                                    opciones={derivacion.usuarios}
                                    value={derivar.data.responsable_id}
                                    onChange={(ev) => derivar.setData('responsable_id', ev.target.value)}
                                />
                            )}
                        </FormField>
                        <fieldset className="flex flex-col gap-1">
                            <legend className="mb-1 text-base font-semibold text-fg">En copia</legend>
                            <p className="mb-1 text-sm text-fg-muted">Opcional: estas áreas lo ven para conocimiento, sin atenderlo.</p>
                            <div className="grid max-h-36 gap-1 overflow-y-auto sm:grid-cols-2">
                                {derivacion.areas
                                    .filter((a) => String(a.value) !== derivar.data.area_id)
                                    .map((a) => (
                                        <Checkbox
                                            key={a.value}
                                            etiqueta={a.label}
                                            checked={derivar.data.areas_copia.includes(a.value)}
                                            onChange={(ev) =>
                                                derivar.setData(
                                                    'areas_copia',
                                                    ev.target.checked ? [...derivar.data.areas_copia, a.value] : derivar.data.areas_copia.filter((id) => id !== a.value),
                                                )
                                            }
                                        />
                                    ))}
                            </div>
                            {derivar.errors.areas_copia && <p className="text-sm text-danger">{derivar.errors.areas_copia}</p>}
                        </fieldset>
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
                            etiqueta="Fecha límite"
                            ayuda="Solo si el documento fija una fecha (reunión, entrega). Vacía: según el plazo del tipo."
                            error={derivar.errors.fecha_limite}
                        >
                            {(c) => <Input {...c} type="date" value={derivar.data.fecha_limite} onChange={(ev) => derivar.setData('fecha_limite', ev.target.value)} />}
                        </FormField>
                        {e.serie && e.serie.expedientes.length > 1 && (
                            <Checkbox
                                etiqueta={`Derivar toda la serie «${e.serie.nombre}» (${e.serie.expedientes.length} documentos)`}
                                checked={derivar.data.toda_la_serie}
                                onChange={(ev) => derivar.setData('toda_la_serie', ev.target.checked)}
                            />
                        )}
                        <Checkbox
                            etiqueta="Requiere respuesta"
                            checked={derivar.data.requiere_respuesta}
                            onChange={(ev) => derivar.setData('requiere_respuesta', ev.target.checked)}
                        />
                        <FormField etiqueta="Nota" error={derivar.errors.nota}>
                            {(c) => <Textarea {...c} value={derivar.data.nota} maxLength={2000} onChange={(ev) => derivar.setData('nota', ev.target.value)} />}
                        </FormField>
                    </div>
                </Dialog>
            )}

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
