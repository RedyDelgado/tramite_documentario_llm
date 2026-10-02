import { useForm } from '@inertiajs/react';
import { FormField } from '@/components/forms/FormField';
import { FormSection } from '@/components/forms/FormSection';
import { FormPage } from '@/components/layouts/FormPage';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Switch } from '@/components/ui/Switch';
import { TagsInput } from '@/components/ui/TagsInput';
import type { Opcion, ReglaDerivacion } from '@/types';

type Props = {
    regla: ReglaDerivacion | null;
    opcionesTipo: Opcion<number>[];
    opcionesArea: Opcion<number>[];
    opcionesUsuario: Opcion<number>[];
};

export default function ReglaDerivacionForm({ regla, opcionesTipo, opcionesArea, opcionesUsuario }: Props) {
    const form = useForm({
        nombre: regla?.nombre ?? '',
        tipo_tramite_id: regla?.tipo_tramite_id ? String(regla.tipo_tramite_id) : '',
        palabras_clave: regla?.palabras_clave ?? [],
        remitentes: regla?.remitentes ?? [],
        area_destino_id: regla ? String(regla.area_destino_id) : '',
        responsable_id: regla?.responsable_id ? String(regla.responsable_id) : '',
        prioridad: String(regla?.prioridad ?? 100),
        activa: regla?.activa ?? true,
    });
    const { data, setData, errors } = form;
    // Laravel informa el error de cada elemento como `lista.N`.
    const errorDe = (campo: string) => errors[campo as keyof typeof errors] ?? Object.entries(errors).find(([k]) => k.startsWith(`${campo}.`))?.[1];

    const enviar = () => {
        form.transform((d) => ({
            ...d,
            tipo_tramite_id: d.tipo_tramite_id ? Number(d.tipo_tramite_id) : null,
            area_destino_id: Number(d.area_destino_id),
            responsable_id: d.responsable_id ? Number(d.responsable_id) : null,
            prioridad: Number(d.prioridad),
        }));
        if (regla) {
            form.put(`/reglas-derivacion/${regla.id}`);
        } else {
            form.post('/reglas-derivacion');
        }
    };

    return (
        <FormPage
            titulo={regla ? `Editar «${regla.nombre}»` : 'Nueva regla de derivación'}
            descripcion="La regla sugiere el área (y el responsable) al derivar; quien deriva decide. Se aplica la primera regla activa que cumpla todas sus condiciones."
            volverA="/reglas-derivacion"
            onEnviar={enviar}
            procesando={form.processing}
        >
            <FormSection titulo="Regla">
                <FormField etiqueta="Nombre" requerido error={errors.nombre} className="md:col-span-2">
                    {(c) => <Input {...c} value={data.nombre} maxLength={150} autoFocus onChange={(e) => setData('nombre', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Prioridad" ayuda="Se evalúa primero el número menor." requerido error={errors.prioridad}>
                    {(c) => <Input {...c} type="number" min={1} max={9999} value={data.prioridad} onChange={(e) => setData('prioridad', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Activa" error={errors.activa}>
                    {(c) => <Switch id={c.id} aria-describedby={c['aria-describedby']} checked={data.activa} onCheckedChange={(v) => setData('activa', v)} />}
                </FormField>
            </FormSection>

            <FormSection titulo="Condiciones" descripcion="Deben cumplirse todas las que indiques; al menos una.">
                <FormField etiqueta="Tipo de trámite" error={errors.tipo_tramite_id} className="md:col-span-2">
                    {(c) => (
                        <Select
                            {...c}
                            vacia="Cualquier tipo"
                            opciones={opcionesTipo}
                            value={data.tipo_tramite_id}
                            onChange={(e) => setData('tipo_tramite_id', e.target.value)}
                        />
                    )}
                </FormField>
                <FormField etiqueta="Palabras clave en el asunto" ayuda="Basta con que aparezca una." error={errorDe('palabras_clave')} className="md:col-span-2">
                    {(c) => (
                        <TagsInput
                            {...c}
                            valor={data.palabras_clave}
                            placeholder="convenio, sílabo, matrícula…"
                            onCambiar={(v) => setData('palabras_clave', v)}
                        />
                    )}
                </FormField>
                <FormField
                    etiqueta="Remitentes"
                    ayuda="Correo exacto o dominio (incluye subdominios). Basta con que coincida uno."
                    error={errorDe('remitentes')}
                    className="md:col-span-2"
                >
                    {(c) => (
                        <TagsInput
                            {...c}
                            valor={data.remitentes}
                            placeholder="mesadepartes@ejemplo.edu.pe, ejemplo.gob.pe…"
                            onCambiar={(v) => setData('remitentes', v)}
                        />
                    )}
                </FormField>
            </FormSection>

            <FormSection titulo="Destino">
                <FormField etiqueta="Área" requerido error={errors.area_destino_id}>
                    {(c) => (
                        <Select
                            {...c}
                            vacia="Elige un área"
                            opciones={opcionesArea}
                            value={data.area_destino_id}
                            onChange={(e) => setData('area_destino_id', e.target.value)}
                        />
                    )}
                </FormField>
                <FormField etiqueta="Responsable" ayuda="Opcional: sin él, lo asigna el área." error={errors.responsable_id}>
                    {(c) => (
                        <Select
                            {...c}
                            vacia="Sin responsable fijo"
                            opciones={opcionesUsuario}
                            value={data.responsable_id}
                            onChange={(e) => setData('responsable_id', e.target.value)}
                        />
                    )}
                </FormField>
            </FormSection>
        </FormPage>
    );
}
