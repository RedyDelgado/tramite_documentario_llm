import { IcoArchivo, IcoCorrecto, IcoDeshacer, IcoProhibido } from '@/components/ui/iconos';
import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/forms/FormField';
import { Button } from '@/components/ui/Button';
import { Dialog } from '@/components/ui/Dialog';
import { Select } from '@/components/ui/Select';
import { Textarea } from '@/components/ui/Textarea';
import { BotonConfirmado } from '@/components/ui/BotonConfirmado';
import type { ExpedienteFila, Opcion, OpcionEmisor } from '@/types';
import { RemitenteCampos } from './RemitenteCampos';

type Props = {
    expediente: Pick<ExpedienteFila, 'id' | 'estado' | 'numero_registro' | 'puede_registrar'>;
    // Desde el detalle se indican emisor y tipo de documento al registrar (6.1); desde la lista, después.
    opciones?: { emisor: OpcionEmisor[]; tipoDocumento: Opcion<number>[] };
};

/** Acciones de registro (6.2) según el estado; la regla final la aplica ExpedienteService. */
export function AccionesRegistro({ expediente, opciones }: Props) {
    const [confirmando, setConfirmando] = useState(false);
    const [anulando, setAnulando] = useState(false);
    const [procesando, setProcesando] = useState(false);
    const anulacion = useForm({ motivo: '' });
    const registro = useForm({ emisor_id: null as number | null, institucion_id: null as number | null, tipo_documento_id: '' });
    const estado = expediente.estado.valor;

    if (!expediente.puede_registrar) return null;

    const ejecutar = (accion: string, alTerminar?: () => void) =>
        router.post(`/expedientes/${expediente.id}/${accion}`, {}, {
            preserveScroll: true,
            onStart: () => setProcesando(true),
            onFinish: () => {
                setProcesando(false);
                alTerminar?.();
            },
        });

    return (
        <>
            {(estado === 'por_revisar' || estado === 'historico') && (
                <>
                    <Button variante="primario" icono={<IcoCorrecto />} onClick={() => setConfirmando(true)}>
                        Registrar como trámite
                    </Button>
                    <BotonConfirmado
                        icono={<IcoArchivo />}
                        titulo="¿Marcar como no trámite?"
                        descripcion="Se archiva sin número ni semáforo. No se borra: se puede devolver a revisión."
                        confirmar="No es trámite"
                        cargando={procesando}
                        onConfirmar={(cerrar) => ejecutar('no-tramite', cerrar)}
                    >
                        No es trámite
                    </BotonConfirmado>
                </>
            )}
            {estado === 'no_tramite' && (
                <BotonConfirmado
                    icono={<IcoDeshacer />}
                    titulo="¿Devolver a revisión?"
                    descripcion="Vuelve a la bandeja por revisar para decidir si es trámite."
                    confirmar="Devolver"
                    cargando={procesando}
                    onConfirmar={(cerrar) => ejecutar('devolver', cerrar)}
                >
                    Devolver a revisión
                </BotonConfirmado>
            )}
            {expediente.numero_registro && estado !== 'anulado' && (
                <Button icono={<IcoProhibido />} onClick={() => setAnulando(true)}>
                    Anular
                </Button>
            )}

            <Dialog
                abierto={confirmando}
                onCambiar={setConfirmando}
                titulo="¿Registrar como trámite?"
                descripcion="Recibirá el siguiente número de registro del año. El número no se libera: si fue un error, el registro se anula y conserva su número."
                pie={
                    <>
                        <Button onClick={() => setConfirmando(false)}>Cancelar</Button>
                        <Button
                            variante="primario"
                            cargando={registro.processing}
                            onClick={() => {
                                registro.transform((d) => ({ ...d, tipo_documento_id: d.tipo_documento_id ? Number(d.tipo_documento_id) : null }));
                                registro.post(`/expedientes/${expediente.id}/confirmar`, {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        setConfirmando(false);
                                        registro.reset();
                                    },
                                });
                            }}
                        >
                            Registrar
                        </Button>
                    </>
                }
            >
                {opciones && (
                    <div className="flex flex-col gap-4">
                        <RemitenteCampos
                            opciones={opciones.emisor}
                            emisorId={registro.data.emisor_id}
                            institucionId={registro.data.institucion_id}
                            onEmisor={(v) => registro.setData('emisor_id', v)}
                            onInstitucion={(v) => registro.setData('institucion_id', v)}
                            errores={{ emisor: registro.errors.emisor_id, institucion: registro.errors.institucion_id }}
                        />
                        <FormField etiqueta="Tipo de documento" error={registro.errors.tipo_documento_id}>
                            {(c) => (
                                <Select
                                    {...c}
                                    vacia="Sin indicar"
                                    opciones={opciones.tipoDocumento}
                                    value={registro.data.tipo_documento_id}
                                    onChange={(e) => registro.setData('tipo_documento_id', e.target.value)}
                                />
                            )}
                        </FormField>
                    </div>
                )}
            </Dialog>
            <Dialog
                abierto={anulando}
                onCambiar={(abierto) => {
                    setAnulando(abierto);
                    if (!abierto) anulacion.reset();
                }}
                titulo={`Anular ${expediente.numero_registro ?? ''}`}
                descripcion="El registro conserva su número, que no se reutilizará. La anulación queda en la auditoría."
                pie={
                    <>
                        <Button onClick={() => setAnulando(false)}>Cancelar</Button>
                        <Button
                            variante="peligro"
                            cargando={anulacion.processing}
                            onClick={() =>
                                anulacion.post(`/expedientes/${expediente.id}/anular`, {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        setAnulando(false);
                                        anulacion.reset();
                                    },
                                })
                            }
                        >
                            Anular registro
                        </Button>
                    </>
                }
            >
                <FormField etiqueta="Motivo" requerido error={anulacion.errors.motivo}>
                    {(c) => (
                        <Textarea
                            {...c}
                            value={anulacion.data.motivo}
                            maxLength={500}
                            onChange={(e) => anulacion.setData('motivo', e.target.value)}
                        />
                    )}
                </FormField>
            </Dialog>
        </>
    );
}
