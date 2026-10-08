import { useForm } from '@inertiajs/react';
import { FormField } from '@/components/forms/FormField';
import { FormSection } from '@/components/forms/FormSection';
import { FormDialog } from '@/components/layouts/FormDialog';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Switch } from '@/components/ui/Switch';
import type { Emisor, Opcion } from '@/types';

type Props = { emisor: Emisor | null; opcionesTipo: Opcion<string>[]; opcionesClase: Opcion<string>[]; opcionesInstitucion: Opcion<number>[] };

export default function EmisorForm({ emisor, opcionesTipo, opcionesClase, opcionesInstitucion, onCerrar }: Props & { onCerrar: () => void }) {
    const form = useForm({
        nombre: emisor?.nombre ?? '',
        tipo: emisor?.tipo ?? 'externo',
        clase: emisor?.clase ?? 'persona',
        institucion_id: emisor?.institucion_id ? String(emisor.institucion_id) : '',
        activo: emisor?.activo ?? true,
    });
    const { data, setData, errors } = form;

    const enviar = () => {
        form.transform((d) => ({ ...d, institucion_id: d.clase === 'persona' ? Number(d.institucion_id) || null : null }));
        return emisor ? form.put(`/emisores/${emisor.id}`) : form.post('/emisores');
    };

    return (
        <FormDialog
            onCerrar={onCerrar}
            titulo={emisor ? `Editar «${emisor.nombre}»` : 'Nuevo emisor'}
            descripcion="Se rechaza un nombre que ya exista aunque cambien tildes, mayúsculas o puntuación."
            onEnviar={enviar}
            procesando={form.processing}
        >
            <FormSection titulo="Emisor">
                <FormField etiqueta="Nombre" requerido error={errors.nombre} className="md:col-span-2">
                    {(c) => <Input {...c} value={data.nombre} maxLength={200} autoFocus onChange={(e) => setData('nombre', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Es" ayuda="Quien firma o la institución que emite sin firma personal." requerido error={errors.clase}>
                    {(c) => <Select {...c} opciones={opcionesClase} value={data.clase} onChange={(e) => setData('clase', e.target.value as Emisor['clase'])} />}
                </FormField>
                <FormField
                    etiqueta="Institución habitual"
                    ayuda={data.clase === 'persona' ? 'A la que pertenece; se propone al registrar. Vacía si no pertenece a ninguna.' : 'Una institución no pertenece a otra.'}
                    error={errors.institucion_id}
                >
                    {(c) => (
                        <Select
                            {...c}
                            vacia="Sin institución"
                            disabled={data.clase !== 'persona'}
                            opciones={opcionesInstitucion.filter((o) => o.value !== emisor?.id)}
                            value={data.clase === 'persona' ? data.institucion_id : ''}
                            onChange={(e) => setData('institucion_id', e.target.value)}
                        />
                    )}
                </FormField>
                <FormField etiqueta="Tipo" ayuda="Interno: de la universidad." requerido error={errors.tipo}>
                    {(c) => <Select {...c} opciones={opcionesTipo} value={data.tipo} onChange={(e) => setData('tipo', e.target.value as Emisor['tipo'])} />}
                </FormField>
                <FormField etiqueta="Activo" ayuda="Un emisor inactivo no aparece al registrar." error={errors.activo}>
                    {(c) => <Switch id={c.id} aria-describedby={c['aria-describedby']} checked={data.activo} onCheckedChange={(v) => setData('activo', v)} />}
                </FormField>
            </FormSection>
        </FormDialog>
    );
}
