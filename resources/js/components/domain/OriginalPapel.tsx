import { ArrowSwap20Regular, Print20Regular, QrCode20Regular } from '@fluentui/react-icons';
import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { DetalleLista } from '@/components/data/DetalleLista';
import { FormField } from '@/components/forms/FormField';
import { Button, botonClases } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Dialog } from '@/components/ui/Dialog';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { formatearFechaHora } from '@/lib/fechas';
import type { ExpedienteDetalle, Opcion } from '@/types';

type Props = { expediente: ExpedienteDetalle; custodia?: { ubicaciones: Opcion<number>[]; usuarios: Opcion<number>[] } };

/** Original en papel y cargos de entrega (7.3.1). El sistema no ofrece descartar un original: solo moverlo. */
export function OriginalPapel({ expediente: e, custodia }: Props) {
    const [moviendo, setMoviendo] = useState(false);
    const [subiendo, setSubiendo] = useState<number | null>(null);
    const [porSubir, setPorSubir] = useState<{ movimiento: number; archivo: File } | null>(null);
    const original = e.original;
    const mover = useForm({
        ubicacion_fisica_id: String(original?.ubicacion_fisica_id ?? ''),
        custodio_id: String(original?.custodio_id ?? ''),
        nota: '',
    });

    if (!original && e.cargos.length === 0) return null;

    const adjuntar = ({ movimiento, archivo }: { movimiento: number; archivo: File }) =>
        router.post(
            `/movimientos/${movimiento}/cargo`,
            { archivo },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setSubiendo(movimiento),
                onFinish: () => {
                    setSubiendo(null);
                    setPorSubir(null);
                },
            },
        );

    return (
        <Card titulo={original ? 'Original en papel' : 'Cargos de entrega'}>
            {original && (
                <>
                    <p className="mb-3 text-base text-fg-muted">El escaneo es una copia digital de consulta. El original se conserva siempre.</p>
                    <DetalleLista
                        items={[
                            { etiqueta: 'Ubicación', valor: original.ubicacion ?? 'Sin asignar' },
                            { etiqueta: 'Custodio', valor: original.custodio ?? 'Sin asignar' },
                        ]}
                    />
                    {e.permisos.custodiar && (
                        <div className="mt-3 flex flex-wrap gap-2">
                            <a href={`/expedientes/${e.id}/constancia`} target="_blank" rel="noreferrer" className={botonClases()}>
                                <Print20Regular />
                                Constancia
                            </a>
                            <a href={`/expedientes/${e.id}/etiqueta`} target="_blank" rel="noreferrer" className={botonClases()}>
                                <QrCode20Regular />
                                Etiqueta QR
                            </a>
                            {custodia && (
                                <Button icono={<ArrowSwap20Regular />} onClick={() => setMoviendo(true)}>
                                    Mover original
                                </Button>
                            )}
                        </div>
                    )}
                </>
            )}

            {e.cargos.length > 0 && (
                <ul className={`flex flex-col gap-2 ${original ? 'mt-4 border-t border-border pt-3' : ''}`}>
                    {e.cargos.map((c) => (
                        <li key={c.id} className="flex flex-wrap items-center justify-between gap-2">
                            <span className="text-base">
                                Cargo a {c.area} · <span className="text-fg-muted">{formatearFechaHora(c.fecha)}</span>
                            </span>
                            {e.permisos.custodiar && (
                                <span className="flex gap-2">
                                    <a href={`/movimientos/${c.id}/cargo`} target="_blank" rel="noreferrer" className={botonClases({ tamano: 'sm' })}>
                                        Imprimir
                                    </a>
                                    {c.firmado ? (
                                        <a href={`/documentos/${c.firmado}/descargar`} className={botonClases({ tamano: 'sm' })}>
                                            Ver firmado
                                        </a>
                                    ) : (
                                        <label className={botonClases({ tamano: 'sm' })}>
                                            {subiendo === c.id ? 'Subiendo…' : 'Adjuntar firmado'}
                                            <input
                                                type="file"
                                                accept="application/pdf,image/png,image/jpeg"
                                                className="sr-only"
                                                onChange={(ev) => ev.target.files?.[0] && setPorSubir({ movimiento: c.id, archivo: ev.target.files[0] })}
                                            />
                                        </label>
                                    )}
                                </span>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            <ConfirmDialog
                abierto={porSubir !== null}
                onCambiar={(abierto) => !abierto && setPorSubir(null)}
                titulo="¿Adjuntar el cargo firmado?"
                descripcion={`Se guarda «${porSubir?.archivo.name ?? ''}» como cargo de esta derivación, con su hash, y queda en la auditoría.`}
                confirmar="Adjuntar"
                cargando={subiendo !== null}
                onConfirmar={() => porSubir && adjuntar(porSubir)}
            />

            {custodia && (
                <Dialog
                    abierto={moviendo}
                    onCambiar={setMoviendo}
                    titulo="Mover el original"
                    descripcion="Cambia dónde está el papel o quién lo tiene. Queda en el historial y en la auditoría."
                    pie={
                        <>
                            <Button onClick={() => setMoviendo(false)}>Cancelar</Button>
                            <Button
                                variante="primario"
                                cargando={mover.processing}
                                onClick={() => {
                                    mover.transform((d) => ({ ubicacion_fisica_id: Number(d.ubicacion_fisica_id) || null, custodio_id: Number(d.custodio_id) || null, nota: d.nota || null }));
                                    mover.post(`/expedientes/${e.id}/original`, { preserveScroll: true, onSuccess: () => setMoviendo(false) });
                                }}
                            >
                                Guardar
                            </Button>
                        </>
                    }
                >
                    <div className="flex flex-col gap-4">
                        <FormField etiqueta="Ubicación" error={mover.errors.ubicacion_fisica_id}>
                            {(c) => (
                                <Select
                                    {...c}
                                    vacia="Sin asignar"
                                    opciones={custodia.ubicaciones}
                                    value={mover.data.ubicacion_fisica_id}
                                    onChange={(ev) => mover.setData('ubicacion_fisica_id', ev.target.value)}
                                />
                            )}
                        </FormField>
                        <FormField etiqueta="Custodio" requerido error={mover.errors.custodio_id}>
                            {(c) => (
                                <Select
                                    {...c}
                                    vacia="Elige quién lo tiene"
                                    opciones={custodia.usuarios}
                                    value={mover.data.custodio_id}
                                    onChange={(ev) => mover.setData('custodio_id', ev.target.value)}
                                />
                            )}
                        </FormField>
                        <FormField etiqueta="Nota" error={mover.errors.nota}>
                            {(c) => <Input {...c} value={mover.data.nota} maxLength={500} onChange={(ev) => mover.setData('nota', ev.target.value)} />}
                        </FormField>
                    </div>
                </Dialog>
            )}
        </Card>
    );
}
