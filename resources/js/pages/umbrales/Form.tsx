import { useForm } from '@inertiajs/react';
import { FormField } from '@/components/forms/FormField';
import { FormSection } from '@/components/forms/FormSection';
import { FormDialog } from '@/components/layouts/FormDialog';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';

export type Umbrales = {
    porcentaje_amarillo: number;
    dias_sin_movimiento: number;
    ia_modo: 'sombra' | 'activo';
    ia_umbral_sugerencia: number;
    ia_umbral_alta: number;
};

export default function UmbralesForm({ umbrales, onCerrar }: { umbrales: Umbrales; onCerrar: () => void }) {
    const form = useForm({
        porcentaje_amarillo: String(umbrales.porcentaje_amarillo),
        dias_sin_movimiento: String(umbrales.dias_sin_movimiento),
        ia_modo: umbrales.ia_modo,
        ia_umbral_sugerencia: String(umbrales.ia_umbral_sugerencia),
        ia_umbral_alta: String(umbrales.ia_umbral_alta),
    });
    const { data, setData, errors } = form;

    const enviar = () => {
        form.transform((d) => ({
            ...d,
            porcentaje_amarillo: Number(d.porcentaje_amarillo),
            dias_sin_movimiento: Number(d.dias_sin_movimiento),
            ia_umbral_sugerencia: Number(d.ia_umbral_sugerencia),
            ia_umbral_alta: Number(d.ia_umbral_alta),
        }));
        form.put('/umbrales', { preserveScroll: true, onSuccess: onCerrar });
    };

    return (
        <FormDialog onCerrar={onCerrar} titulo="Editar umbrales" descripcion="Los cambios rigen desde el siguiente cálculo, sin desplegar." onEnviar={enviar} procesando={form.processing}>
            <FormSection
                titulo="Semáforo: pasa a amarillo"
                descripcion="Verde: en plazo y con responsable. Rojo: plazo vencido o sin responsable. Amarillo: cuando se cumple cualquiera de estos umbrales."
            >
                <FormField
                    etiqueta="Porcentaje del plazo restante"
                    ayuda="Por ejemplo, 30: amarillo cuando queda menos del 30 % del plazo."
                    requerido
                    error={errors.porcentaje_amarillo}
                >
                    {(c) => (
                        <Input
                            {...c}
                            type="number"
                            min={1}
                            max={99}
                            value={data.porcentaje_amarillo}
                            onChange={(e) => setData('porcentaje_amarillo', e.target.value)}
                        />
                    )}
                </FormField>
                <FormField etiqueta="Días sin movimiento" ayuda="Días calendario desde el último movimiento." requerido error={errors.dias_sin_movimiento}>
                    {(c) => (
                        <Input
                            {...c}
                            type="number"
                            min={1}
                            max={365}
                            value={data.dias_sin_movimiento}
                            onChange={(e) => setData('dias_sin_movimiento', e.target.value)}
                        />
                    )}
                </FormField>
            </FormSection>

            <FormSection
                titulo="Clasificación con IA"
                descripcion="En modo sombra la IA solo propone y se mide contra la decisión humana, sin mostrarse. En modo activo su propuesta prellena la derivación; nunca decide sola."
            >
                <FormField etiqueta="Modo" requerido error={errors.ia_modo}>
                    {(c) => (
                        <Select
                            {...c}
                            opciones={[
                                { value: 'sombra', label: 'Sombra (medir sin mostrar)' },
                                { value: 'activo', label: 'Activo (proponer al derivar)' },
                            ]}
                            value={data.ia_modo}
                            onChange={(e) => setData('ia_modo', e.target.value as Umbrales['ia_modo'])}
                        />
                    )}
                </FormField>
                <FormField etiqueta="Confianza mínima para proponer" ayuda="Entre 0 y 1; por defecto 0,60." requerido error={errors.ia_umbral_sugerencia}>
                    {(c) => (
                        <Input
                            {...c}
                            type="number"
                            step="0.01"
                            min={0.05}
                            max={0.99}
                            value={data.ia_umbral_sugerencia}
                            onChange={(e) => setData('ia_umbral_sugerencia', e.target.value)}
                        />
                    )}
                </FormField>
                <FormField etiqueta="Confianza alta" ayuda="Se marca como «alta confianza»; por defecto 0,90." requerido error={errors.ia_umbral_alta}>
                    {(c) => (
                        <Input
                            {...c}
                            type="number"
                            step="0.01"
                            min={0.05}
                            max={0.99}
                            value={data.ia_umbral_alta}
                            onChange={(e) => setData('ia_umbral_alta', e.target.value)}
                        />
                    )}
                </FormField>
            </FormSection>
        </FormDialog>
    );
}
