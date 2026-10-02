import { useForm } from '@inertiajs/react';
import { FormField } from '@/components/forms/FormField';
import { FormSection } from '@/components/forms/FormSection';
import { FormPage } from '@/components/layouts/FormPage';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Switch } from '@/components/ui/Switch';
import type { TipoDocumento } from '@/types';

export default function TipoDocumentoForm({ tipo }: { tipo: TipoDocumento | null }) {
    const form = useForm({
        nombre: tipo?.nombre ?? '',
        formato_numero: tipo?.formato_numero ?? '{TIPO} N.º {NUMERO}-{ANIO}-{AREA}',
        aprueba_salida: tipo?.aprueba_salida ?? 'director',
        activo: tipo?.activo ?? true,
    });
    const { data, setData, errors } = form;

    const enviar = () => (tipo ? form.put(`/tipos-documento/${tipo.id}`) : form.post('/tipos-documento'));

    return (
        <FormPage titulo={tipo ? `Editar «${tipo.nombre}»` : 'Nuevo tipo de documento'} volverA="/tipos-documento" onEnviar={enviar} procesando={form.processing}>
            <FormSection titulo="Tipo de documento">
                <FormField etiqueta="Nombre" requerido error={errors.nombre}>
                    {(c) => <Input {...c} value={data.nombre} maxLength={100} autoFocus onChange={(e) => setData('nombre', e.target.value)} />}
                </FormField>
                <FormField
                    etiqueta="Formato de numeración"
                    ayuda="Para lo que emite la institución: {TIPO}, {NUMERO} (correlativo por tipo, área y año), {ANIO} y {AREA} (siglas)."
                    requerido
                    error={errors.formato_numero}
                >
                    {(c) => <Input {...c} value={data.formato_numero} maxLength={100} onChange={(e) => setData('formato_numero', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Aprueba la salida" requerido error={errors.aprueba_salida}>
                    {(c) => (
                        <Select
                            {...c}
                            opciones={[
                                { value: 'director', label: 'Director' },
                                { value: 'coordinador', label: 'Coordinador del área que emite' },
                            ]}
                            value={data.aprueba_salida}
                            onChange={(e) => setData('aprueba_salida', e.target.value as TipoDocumento['aprueba_salida'])}
                        />
                    )}
                </FormField>
                <FormField etiqueta="Activo" ayuda="Un tipo inactivo no aparece al registrar." error={errors.activo}>
                    {(c) => <Switch id={c.id} aria-describedby={c['aria-describedby']} checked={data.activo} onCheckedChange={(v) => setData('activo', v)} />}
                </FormField>
            </FormSection>
        </FormPage>
    );
}
