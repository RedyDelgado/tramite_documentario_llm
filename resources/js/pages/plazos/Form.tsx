import { useForm } from '@inertiajs/react';
import { FormField } from '@/components/forms/FormField';
import { FormSection } from '@/components/forms/FormSection';
import { FormDialog } from '@/components/layouts/FormDialog';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import type { Opcion, PlazoArea } from '@/types';

type Props = { plazo: PlazoArea | null; opcionesTipo: Opcion<number>[]; opcionesArea: Opcion<number>[] };

export default function PlazoAreaForm({ plazo, opcionesTipo, opcionesArea, onCerrar }: Props & { onCerrar: () => void }) {
    const form = useForm({
        tipo_tramite_id: plazo ? String(plazo.tipo_tramite_id) : '',
        area_id: plazo ? String(plazo.area_id) : '',
        plazo_dias: plazo ? String(plazo.plazo_dias) : '',
    });
    const { data, setData, errors } = form;

    const enviar = () => {
        form.transform((d) => ({ tipo_tramite_id: Number(d.tipo_tramite_id), area_id: Number(d.area_id), plazo_dias: Number(d.plazo_dias) }));
        if (plazo) {
            form.put(`/plazos/${plazo.id}`);
        } else {
            form.post('/plazos');
        }
    };

    return (
        <FormDialog
            onCerrar={onCerrar}
            titulo={plazo ? `Plazo de «${plazo.tipo}» en ${plazo.area}` : 'Nuevo plazo por área'}
            descripcion="Se cuenta igual que el tipo (hábiles o calendario). Los expedientes ya ingresados conservan su plazo."
            onEnviar={enviar}
            procesando={form.processing}
        >
            <FormSection titulo="Plazo">
                <FormField etiqueta="Tipo de trámite" requerido error={errors.tipo_tramite_id}>
                    {(c) => (
                        <Select
                            {...c}
                            vacia="Elige un tipo"
                            opciones={opcionesTipo}
                            value={data.tipo_tramite_id}
                            onChange={(e) => setData('tipo_tramite_id', e.target.value)}
                        />
                    )}
                </FormField>
                <FormField etiqueta="Área" requerido error={errors.area_id}>
                    {(c) => <Select {...c} vacia="Elige un área" opciones={opcionesArea} value={data.area_id} onChange={(e) => setData('area_id', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Plazo en días" requerido error={errors.plazo_dias}>
                    {(c) => <Input {...c} type="number" min={1} max={365} value={data.plazo_dias} onChange={(e) => setData('plazo_dias', e.target.value)} />}
                </FormField>
            </FormSection>
        </FormDialog>
    );
}
