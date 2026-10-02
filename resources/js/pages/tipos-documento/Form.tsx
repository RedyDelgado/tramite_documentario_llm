import { useForm } from '@inertiajs/react';
import { FormField } from '@/components/forms/FormField';
import { FormSection } from '@/components/forms/FormSection';
import { FormPage } from '@/components/layouts/FormPage';
import { Input } from '@/components/ui/Input';
import { Switch } from '@/components/ui/Switch';
import type { TipoDocumento } from '@/types';

export default function TipoDocumentoForm({ tipo }: { tipo: TipoDocumento | null }) {
    const form = useForm({ nombre: tipo?.nombre ?? '', activo: tipo?.activo ?? true });
    const { data, setData, errors } = form;

    const enviar = () => (tipo ? form.put(`/tipos-documento/${tipo.id}`) : form.post('/tipos-documento'));

    return (
        <FormPage titulo={tipo ? `Editar «${tipo.nombre}»` : 'Nuevo tipo de documento'} volverA="/tipos-documento" onEnviar={enviar} procesando={form.processing}>
            <FormSection titulo="Tipo de documento">
                <FormField etiqueta="Nombre" requerido error={errors.nombre}>
                    {(c) => <Input {...c} value={data.nombre} maxLength={100} autoFocus onChange={(e) => setData('nombre', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Activo" ayuda="Un tipo inactivo no aparece al registrar." error={errors.activo}>
                    {(c) => <Switch id={c.id} aria-describedby={c['aria-describedby']} checked={data.activo} onCheckedChange={(v) => setData('activo', v)} />}
                </FormField>
            </FormSection>
        </FormPage>
    );
}
