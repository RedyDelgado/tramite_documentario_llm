import { useForm } from '@inertiajs/react';
import { FormField } from '@/components/forms/FormField';
import { FormSection } from '@/components/forms/FormSection';
import { FormDialog } from '@/components/layouts/FormDialog';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import type { Opcion } from '@/types';

type Inicial = { tipo?: string; tipo_documento_id?: string; area_id?: string; anio?: string; siguiente?: string };

type Props = { opcionesTipoDocumento: Opcion<number>[]; opcionesArea: Opcion<number>[]; inicial: Inicial };

const TIPOS = [
    { value: 'registro', label: 'Registro de documentos recibidos' },
    { value: 'saliente', label: 'Documentos emitidos (por tipo y área)' },
];

export default function NumeracionForm({ opcionesTipoDocumento, opcionesArea, inicial, onCerrar }: Props & { onCerrar: () => void }) {
    const form = useForm({
        tipo: inicial.tipo ?? 'registro',
        tipo_documento_id: inicial.tipo_documento_id ?? '',
        area_id: inicial.area_id ?? '',
        anio: inicial.anio ?? String(new Date().getFullYear()),
        siguiente: inicial.siguiente ?? '',
    });
    const { data, setData, errors } = form;

    const enviar = () => {
        form.transform((d) => ({
            ...d,
            tipo_documento_id: Number(d.tipo_documento_id) || null,
            area_id: Number(d.area_id) || null,
            anio: Number(d.anio) || null,
            siguiente: Number(d.siguiente) || null,
        }));
        form.post('/numeracion');
    };

    return (
        <FormDialog
            onCerrar={onCerrar}
            titulo="Ajustar numeración"
            descripcion="Fija con qué número continúa el correlativo, por ejemplo para seguir el que se llevaba en papel. No puede ser igual ni menor a uno ya usado."
            onEnviar={enviar}
            procesando={form.processing}
        >
            <FormSection titulo="Correlativo">
                <FormField etiqueta="Correlativo" requerido error={errors.tipo} className="md:col-span-2">
                    {(c) => <Select {...c} opciones={TIPOS} value={data.tipo} onChange={(e) => setData('tipo', e.target.value)} />}
                </FormField>
                {data.tipo === 'saliente' && (
                    <>
                        <FormField etiqueta="Tipo de documento" requerido error={errors.tipo_documento_id}>
                            {(c) => (
                                <Select
                                    {...c}
                                    vacia="Elige el tipo"
                                    opciones={opcionesTipoDocumento}
                                    value={data.tipo_documento_id}
                                    onChange={(e) => setData('tipo_documento_id', e.target.value)}
                                />
                            )}
                        </FormField>
                        <FormField etiqueta="Área que emite" requerido error={errors.area_id}>
                            {(c) => <Select {...c} vacia="Elige el área" opciones={opcionesArea} value={data.area_id} onChange={(e) => setData('area_id', e.target.value)} />}
                        </FormField>
                    </>
                )}
                <FormField etiqueta="Año" requerido error={errors.anio}>
                    {(c) => <Input {...c} type="number" min={2000} max={2100} value={data.anio} onChange={(e) => setData('anio', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Siguiente número" ayuda="El que tendrá el próximo documento." requerido error={errors.siguiente}>
                    {(c) => <Input {...c} type="number" min={1} max={99999} value={data.siguiente} autoFocus onChange={(e) => setData('siguiente', e.target.value)} />}
                </FormField>
            </FormSection>
        </FormDialog>
    );
}
