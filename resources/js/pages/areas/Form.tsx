import { useForm } from '@inertiajs/react';
import { FormField } from '@/components/forms/FormField';
import { FormSection } from '@/components/forms/FormSection';
import { FormPage } from '@/components/layouts/FormPage';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Switch } from '@/components/ui/Switch';
import { TagsInput } from '@/components/ui/TagsInput';
import { Textarea } from '@/components/ui/Textarea';
import type { Area, Opcion } from '@/types';

type Props = { area: Area | null; opcionesPadre: Opcion<number>[] };

export default function AreaForm({ area, opcionesPadre }: Props) {
    const form = useForm({
        nombre: area?.nombre ?? '',
        siglas: area?.siglas ?? '',
        descripcion: area?.descripcion ?? '',
        palabras_clave: area?.palabras_clave ?? [],
        parent_id: area?.parent_id ? String(area.parent_id) : '',
        orden: String(area?.orden ?? 0),
        activa: area?.activa ?? true,
    });
    const { data, setData, errors } = form;
    const errorPalabras = errors.palabras_clave ?? Object.entries(errors).find(([k]) => k.startsWith('palabras_clave.'))?.[1];

    const enviar = () => {
        form.transform((d) => ({ ...d, parent_id: d.parent_id ? Number(d.parent_id) : null, siglas: d.siglas || null, orden: Number(d.orden) }));
        if (area) {
            form.put(`/areas/${area.id}`);
        } else {
            form.post('/areas');
        }
    };

    return (
        <FormPage
            titulo={area ? `Editar «${area.nombre}»` : 'Nueva área'}
            descripcion="Los cambios surten efecto de inmediato y no alteran los expedientes ya ingresados."
            volverA="/areas"
            onEnviar={enviar}
            procesando={form.processing}
        >
            <FormSection titulo="Datos del área">
                <FormField etiqueta="Nombre" requerido error={errors.nombre}>
                    {(c) => <Input {...c} value={data.nombre} maxLength={150} autoFocus onChange={(e) => setData('nombre', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Siglas" ayuda="Van en el número de lo que emite el área, p. ej. DGA." error={errors.siglas}>
                    {(c) => <Input {...c} value={data.siglas} maxLength={20} onChange={(e) => setData('siglas', e.target.value)} />}
                </FormField>
                <FormField
                    etiqueta="Descripción"
                    ayuda="Una buena descripción permite que la IA sugiera esta área aunque aún no tenga ejemplos."
                    error={errors.descripcion}
                    className="md:col-span-2"
                >
                    {(c) => <Textarea {...c} value={data.descripcion} maxLength={2000} onChange={(e) => setData('descripcion', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Área superior" ayuda="Por ejemplo, la escuela de la que depende." error={errors.parent_id}>
                    {(c) => (
                        <Select
                            {...c}
                            vacia="Ninguna (área principal)"
                            opciones={opcionesPadre}
                            value={data.parent_id}
                            onChange={(e) => setData('parent_id', e.target.value)}
                        />
                    )}
                </FormField>
                <FormField etiqueta="Orden" ayuda="Posición en listas y menús." requerido error={errors.orden}>
                    {(c) => <Input {...c} type="number" min={0} max={9999} value={data.orden} onChange={(e) => setData('orden', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Activa" ayuda="Un área inactiva no recibe nuevas derivaciones; nunca se elimina." error={errors.activa}>
                    {(c) => <Switch id={c.id} aria-describedby={c['aria-describedby']} checked={data.activa} onCheckedChange={(v) => setData('activa', v)} />}
                </FormField>
            </FormSection>

            <FormSection titulo="Clasificación" descripcion="Términos frecuentes en los documentos que corresponden a esta área.">
                <FormField etiqueta="Palabras clave" ayuda="Enter o coma para agregar; Retroceso para quitar la última." error={errorPalabras} className="md:col-span-2">
                    {(c) => (
                        <TagsInput
                            {...c}
                            valor={data.palabras_clave}
                            placeholder="oficio, convenio, sílabo…"
                            onCambiar={(v) => setData('palabras_clave', v)}
                        />
                    )}
                </FormField>
            </FormSection>
        </FormPage>
    );
}
