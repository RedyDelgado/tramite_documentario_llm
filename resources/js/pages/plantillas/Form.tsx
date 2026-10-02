import { useForm } from '@inertiajs/react';
import { FormField } from '@/components/forms/FormField';
import { FormSection } from '@/components/forms/FormSection';
import { FormDialog } from '@/components/layouts/FormDialog';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Switch } from '@/components/ui/Switch';
import { Textarea } from '@/components/ui/Textarea';
import type { Opcion, Plantilla } from '@/types';

type Props = {
    plantilla: (Plantilla & { asunto: string; cuerpo: string }) | null;
    opcionesTipo: Opcion<number>[];
    variables: { variable: string; descripcion: string }[];
};

export default function PlantillaForm({ plantilla, opcionesTipo, variables, onCerrar }: Props & { onCerrar: () => void }) {
    const form = useForm({
        nombre: plantilla?.nombre ?? '',
        tipo_documento_id: plantilla ? String(plantilla.tipo_documento_id) : '',
        asunto: plantilla?.asunto ?? '',
        cuerpo: plantilla?.cuerpo ?? '',
        activa: plantilla?.activa ?? true,
    });
    const { data, setData, errors } = form;

    const enviar = () => {
        form.transform((d) => ({ ...d, tipo_documento_id: Number(d.tipo_documento_id) || null }));
        if (plantilla) {
            form.put(`/plantillas/${plantilla.id}`);
        } else {
            form.post('/plantillas');
        }
    };

    return (
        <FormDialog
            onCerrar={onCerrar}
            titulo={plantilla ? `Editar «${plantilla.nombre}»` : 'Nueva plantilla'}
            descripcion="Cambiar una plantilla no altera los documentos ya redactados."
            onEnviar={enviar}
            procesando={form.processing}
        >
            <FormSection titulo="Plantilla">
                <FormField etiqueta="Nombre" requerido error={errors.nombre}>
                    {(c) => <Input {...c} value={data.nombre} maxLength={150} autoFocus onChange={(e) => setData('nombre', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Tipo de documento" requerido error={errors.tipo_documento_id}>
                    {(c) => (
                        <Select
                            {...c}
                            vacia="Elige el tipo"
                            opciones={opcionesTipo}
                            value={data.tipo_documento_id}
                            onChange={(e) => setData('tipo_documento_id', e.target.value)}
                        />
                    )}
                </FormField>
                <FormField etiqueta="Activa" error={errors.activa}>
                    {(c) => <Switch id={c.id} aria-describedby={c['aria-describedby']} checked={data.activa} onCheckedChange={(v) => setData('activa', v)} />}
                </FormField>
            </FormSection>

            <FormSection titulo="Contenido" descripcion={`Variables: ${variables.map((v) => `${v.variable} (${v.descripcion.toLowerCase()})`).join(', ')}.`}>
                <FormField etiqueta="Asunto" requerido error={errors.asunto} className="md:col-span-2">
                    {(c) => <Input {...c} value={data.asunto} maxLength={300} onChange={(e) => setData('asunto', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Cuerpo" ayuda="Una línea en blanco separa párrafos." requerido error={errors.cuerpo} className="md:col-span-2">
                    {(c) => <Textarea {...c} rows={14} value={data.cuerpo} maxLength={20000} onChange={(e) => setData('cuerpo', e.target.value)} />}
                </FormField>
            </FormSection>
        </FormDialog>
    );
}
