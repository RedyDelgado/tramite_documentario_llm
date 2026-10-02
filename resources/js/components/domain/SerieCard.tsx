import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FormField } from '@/components/forms/FormField';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Checkbox } from '@/components/ui/Checkbox';
import { Dialog } from '@/components/ui/Dialog';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import type { ExpedienteDetalle, OpcionesAgrupacion, ResumenExpediente } from '@/types';

type Props = { expediente: ExpedienteDetalle; agrupacion?: OpcionesAgrupacion };

function Lista({ expedientes, actual }: { expedientes: ResumenExpediente[]; actual?: number }) {
    return (
        <ul className="flex flex-col gap-1">
            {expedientes.map((x) => (
                <li key={x.id} className="text-base">
                    {x.id === actual ? (
                        <span className="font-semibold">{x.numero_registro ?? 'Sin número'} (este)</span>
                    ) : (
                        <Link href={`/expedientes/${x.id}`} className="text-primary-700 hover:underline">
                            {x.numero_registro ?? 'Sin número'}
                        </Link>
                    )}{' '}
                    <span className="text-fg-muted">· {x.estado}</span>
                </li>
            ))}
        </ul>
    );
}

/** Serie de documentos relacionados (7.3.1): se atienden como unidad sin perder cada registro. */
export function SerieCard({ expediente: e, agrupacion }: Props) {
    const [abierto, setAbierto] = useState(false);
    const form = useForm({ grupo_id: '', nombre: e.asunto.slice(0, 200), incluir: (agrupacion?.parecidos ?? []).map((p) => p.id) });

    if (e.serie) {
        return (
            <Card
                titulo={`Serie: ${e.serie.nombre}`}
                acciones={
                    e.permisos.agrupar && (
                        <Button tamano="sm" onClick={() => router.delete(`/expedientes/${e.id}/serie`, { preserveScroll: true })}>
                            Quitar de la serie
                        </Button>
                    )
                }
            >
                <Lista expedientes={e.serie.expedientes} actual={e.id} />
            </Card>
        );
    }

    if (!agrupacion || !e.permisos.agrupar) return null;
    const parecidos = agrupacion.parecidos;

    return (
        <Card titulo="Serie">
            {parecidos.length > 0 ? (
                <>
                    <p className="mb-2 text-base">
                        Hay {parecidos.length} {parecidos.length === 1 ? 'documento' : 'documentos'} del mismo emisor y asunto, del mismo día:
                    </p>
                    <Lista expedientes={parecidos} />
                </>
            ) : (
                <p className="text-base text-fg-muted">No pertenece a una serie.</p>
            )}
            <Button className="mt-3" onClick={() => setAbierto(true)}>
                {parecidos.length > 0 ? 'Agrupar en una serie' : 'Agregar a una serie'}
            </Button>

            <Dialog
                abierto={abierto}
                onCambiar={setAbierto}
                titulo="Agrupar en una serie"
                descripcion="La serie se deriva una sola vez para todo el lote; cada documento conserva su número y su historial."
                pie={
                    <>
                        <Button onClick={() => setAbierto(false)}>Cancelar</Button>
                        <Button
                            variante="primario"
                            cargando={form.processing}
                            onClick={() => {
                                form.transform((d) => ({ grupo_id: Number(d.grupo_id) || null, nombre: d.grupo_id ? null : d.nombre, incluir: d.incluir }));
                                form.post(`/expedientes/${e.id}/serie`, { preserveScroll: true, onSuccess: () => setAbierto(false) });
                            }}
                        >
                            Agrupar
                        </Button>
                    </>
                }
            >
                <div className="flex flex-col gap-4">
                    <FormField etiqueta="Serie existente" error={form.errors.grupo_id}>
                        {(c) => (
                            <Select
                                {...c}
                                vacia="Crear una serie nueva"
                                opciones={agrupacion.series}
                                value={form.data.grupo_id}
                                onChange={(ev) => form.setData('grupo_id', ev.target.value)}
                            />
                        )}
                    </FormField>
                    {!form.data.grupo_id && (
                        <FormField etiqueta="Nombre de la serie nueva" requerido error={form.errors.nombre}>
                            {(c) => <Input {...c} value={form.data.nombre} maxLength={200} onChange={(ev) => form.setData('nombre', ev.target.value)} />}
                        </FormField>
                    )}
                    {parecidos.map((p) => (
                        <Checkbox
                            key={p.id}
                            etiqueta={`Incluir ${p.numero_registro ?? 'sin número'}`}
                            checked={form.data.incluir.includes(p.id)}
                            onChange={(ev) =>
                                form.setData('incluir', ev.target.checked ? [...form.data.incluir, p.id] : form.data.incluir.filter((id) => id !== p.id))
                            }
                        />
                    ))}
                </div>
            </Dialog>
        </Card>
    );
}
