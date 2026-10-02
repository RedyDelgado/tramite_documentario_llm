import { useForm } from '@inertiajs/react';
import { FormField } from '@/components/forms/FormField';
import { FormSection } from '@/components/forms/FormSection';
import { FormPage } from '@/components/layouts/FormPage';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import type { Opcion, Responsable } from '@/types';

type Props = {
    responsable: Responsable | null;
    opcionesArea: Opcion<number>[];
    opcionesUsuario: Opcion<number>[];
    opcionesTipo: Opcion<string>[];
};

const hoy = () => new Date().toLocaleDateString('en-CA', { timeZone: 'America/Lima' });

export default function ResponsableForm({ responsable, opcionesArea, opcionesUsuario, opcionesTipo }: Props) {
    const form = useForm({
        area_id: responsable ? String(responsable.area_id) : '',
        user_id: responsable ? String(responsable.user_id) : '',
        tipo: (responsable?.tipo ?? 'titular') as string,
        vigente_desde: responsable?.vigente_desde ?? hoy(),
        vigente_hasta: responsable?.vigente_hasta ?? '',
    });
    const { data, setData, errors } = form;

    const enviar = () => {
        form.transform((d) => ({ ...d, area_id: Number(d.area_id), user_id: Number(d.user_id), vigente_hasta: d.vigente_hasta || null }));
        if (responsable) {
            form.put(`/responsables/${responsable.id}`);
        } else {
            form.post('/responsables');
        }
    };

    return (
        <FormPage
            titulo={responsable ? `${responsable.usuario} en ${responsable.area}` : 'Asignar responsable'}
            descripcion="Para que alguien deje de ser responsable, pon la fecha en «Vigente hasta»: el historial se conserva."
            volverA="/responsables"
            onEnviar={enviar}
            procesando={form.processing}
        >
            <FormSection titulo="Responsable">
                <FormField etiqueta="Área" requerido error={errors.area_id}>
                    {(c) => <Select {...c} vacia="Elige un área" opciones={opcionesArea} value={data.area_id} onChange={(e) => setData('area_id', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Usuario" requerido error={errors.user_id}>
                    {(c) => (
                        <Select {...c} vacia="Elige un usuario" opciones={opcionesUsuario} value={data.user_id} onChange={(e) => setData('user_id', e.target.value)} />
                    )}
                </FormField>
                <FormField etiqueta="Tipo" ayuda="Un solo titular a la vez; los suplentes cubren vacaciones y ausencias." requerido error={errors.tipo}>
                    {(c) => <Select {...c} opciones={opcionesTipo} value={data.tipo} onChange={(e) => setData('tipo', e.target.value)} />}
                </FormField>
            </FormSection>

            <FormSection titulo="Vigencia">
                <FormField etiqueta="Vigente desde" requerido error={errors.vigente_desde}>
                    {(c) => <Input {...c} type="date" value={data.vigente_desde} onChange={(e) => setData('vigente_desde', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Vigente hasta" ayuda="Vacío: sin fecha de fin." error={errors.vigente_hasta}>
                    {(c) => <Input {...c} type="date" value={data.vigente_hasta} onChange={(e) => setData('vigente_hasta', e.target.value)} />}
                </FormField>
            </FormSection>
        </FormPage>
    );
}
