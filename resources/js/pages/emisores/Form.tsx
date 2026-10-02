import { useForm } from '@inertiajs/react';
import { FormField } from '@/components/forms/FormField';
import { FormSection } from '@/components/forms/FormSection';
import { FormPage } from '@/components/layouts/FormPage';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Switch } from '@/components/ui/Switch';
import type { Emisor, Opcion } from '@/types';

type Props = { emisor: Emisor | null; opcionesTipo: Opcion<string>[] };

export default function EmisorForm({ emisor, opcionesTipo }: Props) {
    const form = useForm({ nombre: emisor?.nombre ?? '', tipo: emisor?.tipo ?? 'externo', activo: emisor?.activo ?? true });
    const { data, setData, errors } = form;

    const enviar = () => (emisor ? form.put(`/emisores/${emisor.id}`) : form.post('/emisores'));

    return (
        <FormPage
            titulo={emisor ? `Editar «${emisor.nombre}»` : 'Nuevo emisor'}
            descripcion="Se rechaza un nombre que ya exista aunque cambien tildes, mayúsculas o puntuación."
            volverA="/emisores"
            onEnviar={enviar}
            procesando={form.processing}
        >
            <FormSection titulo="Emisor">
                <FormField etiqueta="Nombre" requerido error={errors.nombre} className="md:col-span-2">
                    {(c) => <Input {...c} value={data.nombre} maxLength={200} autoFocus onChange={(e) => setData('nombre', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Tipo" ayuda="Interno: dependencia de la institución." requerido error={errors.tipo}>
                    {(c) => <Select {...c} opciones={opcionesTipo} value={data.tipo} onChange={(e) => setData('tipo', e.target.value as Emisor['tipo'])} />}
                </FormField>
                <FormField etiqueta="Activo" ayuda="Un emisor inactivo no aparece al registrar." error={errors.activo}>
                    {(c) => <Switch id={c.id} aria-describedby={c['aria-describedby']} checked={data.activo} onCheckedChange={(v) => setData('activo', v)} />}
                </FormField>
            </FormSection>
        </FormPage>
    );
}
