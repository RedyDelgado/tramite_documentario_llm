import { Archive20Regular, ArrowUndo20Regular, CheckmarkCircle20Regular, Prohibited20Regular } from '@fluentui/react-icons';
import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/forms/FormField';
import { Button } from '@/components/ui/Button';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Dialog } from '@/components/ui/Dialog';
import { Textarea } from '@/components/ui/Textarea';
import type { ExpedienteFila } from '@/types';

type Props = { expediente: Pick<ExpedienteFila, 'id' | 'estado' | 'numero_registro' | 'puede_registrar'> };

/** Acciones de registro (6.2) según el estado; la regla final la aplica ExpedienteService. */
export function AccionesRegistro({ expediente }: Props) {
    const [confirmando, setConfirmando] = useState(false);
    const [anulando, setAnulando] = useState(false);
    const [procesando, setProcesando] = useState(false);
    const anulacion = useForm({ motivo: '' });
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
                    <Button variante="primario" icono={<CheckmarkCircle20Regular />} onClick={() => setConfirmando(true)}>
                        Registrar como trámite
                    </Button>
                    <Button icono={<Archive20Regular />} cargando={procesando && !confirmando} onClick={() => ejecutar('no-tramite')}>
                        No es trámite
                    </Button>
                </>
            )}
            {estado === 'no_tramite' && (
                <Button icono={<ArrowUndo20Regular />} cargando={procesando} onClick={() => ejecutar('devolver')}>
                    Devolver a revisión
                </Button>
            )}
            {expediente.numero_registro && estado !== 'anulado' && (
                <Button icono={<Prohibited20Regular />} onClick={() => setAnulando(true)}>
                    Anular
                </Button>
            )}

            <ConfirmDialog
                abierto={confirmando}
                onCambiar={setConfirmando}
                titulo="¿Registrar como trámite?"
                descripcion="Recibirá el siguiente número de registro del año. El número no se libera: si fue un error, el registro se anula y conserva su número."
                confirmar="Registrar"
                cargando={procesando}
                onConfirmar={() => ejecutar('confirmar', () => setConfirmando(false))}
            />
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
