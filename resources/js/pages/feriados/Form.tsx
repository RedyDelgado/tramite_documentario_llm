import { useForm } from '@inertiajs/react';
import { FormField } from '@/components/forms/FormField';
import { FormSection } from '@/components/forms/FormSection';
import { FormPage } from '@/components/layouts/FormPage';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import type { Feriado, Opcion } from '@/types';

type Props = { feriado: Feriado | null; opcionesArea: Opcion<number>[] };

export default function FeriadoForm({ feriado, opcionesArea }: Props) {
    const form = useForm({
        fecha: feriado?.fecha ?? '',
        descripcion: feriado?.descripcion ?? '',
        area_id: feriado?.area_id ? String(feriado.area_id) : '',
    });
    const { data, setData, errors } = form;

    const enviar = () => {
        form.transform((d) => ({ ...d, area_id: d.area_id ? Number(d.area_id) : null }));
        if (feriado) {
            form.put(`/feriados/${feriado.id}`);
        } else {
            form.post('/feriados');
        }
    };

    return (
        <FormPage
            titulo={feriado ? `Editar «${feriado.descripcion}»` : 'Nuevo feriado'}
            descripcion="Cuenta para los plazos en días hábiles que se calculen desde ahora; no cambia fechas límite ya asignadas."
            volverA="/feriados"
            onEnviar={enviar}
            procesando={form.processing}
        >
            <FormSection titulo="Feriado">
                <FormField etiqueta="Fecha" requerido error={errors.fecha}>
                    {(c) => <Input {...c} type="date" value={data.fecha} autoFocus onChange={(e) => setData('fecha', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Alcance" ayuda="Un área concreta, p. ej. el aniversario de una filial." error={errors.area_id}>
                    {(c) => (
                        <Select {...c} vacia="Toda la institución" opciones={opcionesArea} value={data.area_id} onChange={(e) => setData('area_id', e.target.value)} />
                    )}
                </FormField>
                <FormField etiqueta="Descripción" requerido error={errors.descripcion} className="md:col-span-2">
                    {(c) => <Input {...c} value={data.descripcion} maxLength={150} placeholder="Fiestas Patrias" onChange={(e) => setData('descripcion', e.target.value)} />}
                </FormField>
            </FormSection>
        </FormPage>
    );
}
