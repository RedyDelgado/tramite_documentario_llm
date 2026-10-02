import { useForm } from '@inertiajs/react';
import { FormField } from '@/components/forms/FormField';
import { FormSection } from '@/components/forms/FormSection';
import { FormPage } from '@/components/layouts/FormPage';
import { Input } from '@/components/ui/Input';
import { Switch } from '@/components/ui/Switch';
import type { UbicacionFisica } from '@/types';

export default function UbicacionForm({ ubicacion }: { ubicacion: UbicacionFisica | null }) {
    const form = useForm({ nombre: ubicacion?.nombre ?? '', descripcion: ubicacion?.descripcion ?? '', activa: ubicacion?.activa ?? true });
    const { data, setData, errors } = form;

    const enviar = () => {
        form.transform((d) => ({ ...d, descripcion: d.descripcion || null }));
        if (ubicacion) {
            form.put(`/ubicaciones/${ubicacion.id}`);
        } else {
            form.post('/ubicaciones');
        }
    };

    return (
        <FormPage titulo={ubicacion ? `Editar «${ubicacion.nombre}»` : 'Nueva ubicación'} volverA="/ubicaciones" onEnviar={enviar} procesando={form.processing}>
            <FormSection titulo="Ubicación">
                <FormField etiqueta="Nombre" requerido error={errors.nombre}>
                    {(c) => <Input {...c} value={data.nombre} maxLength={150} autoFocus onChange={(e) => setData('nombre', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Activa" ayuda="Una ubicación inactiva no se ofrece al mover originales." error={errors.activa}>
                    {(c) => <Switch id={c.id} aria-describedby={c['aria-describedby']} checked={data.activa} onCheckedChange={(v) => setData('activa', v)} />}
                </FormField>
                <FormField etiqueta="Descripción" error={errors.descripcion} className="md:col-span-2">
                    {(c) => <Input {...c} value={data.descripcion} maxLength={300} onChange={(e) => setData('descripcion', e.target.value)} />}
                </FormField>
            </FormSection>
        </FormPage>
    );
}
