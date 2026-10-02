import { useForm } from '@inertiajs/react';
import { FormField } from '@/components/forms/FormField';
import { FormSection } from '@/components/forms/FormSection';
import { FormPage } from '@/components/layouts/FormPage';
import { Input } from '@/components/ui/Input';

type Umbrales = { porcentaje_amarillo: number; dias_sin_movimiento: number };

export default function UmbralesForm({ umbrales }: { umbrales: Umbrales }) {
    const form = useForm({
        porcentaje_amarillo: String(umbrales.porcentaje_amarillo),
        dias_sin_movimiento: String(umbrales.dias_sin_movimiento),
    });
    const { data, setData, errors } = form;

    const enviar = () => {
        form.transform((d) => ({ porcentaje_amarillo: Number(d.porcentaje_amarillo), dias_sin_movimiento: Number(d.dias_sin_movimiento) }));
        form.put('/umbrales', { preserveScroll: true });
    };

    return (
        <FormPage
            titulo="Umbrales del semáforo"
            descripcion="Verde: en plazo y con responsable. Rojo: plazo vencido o sin responsable. Amarillo: cuando se cumple cualquiera de estos umbrales. Los cambios rigen desde el siguiente cálculo."
            volverA="/"
            onEnviar={enviar}
            procesando={form.processing}
        >
            <FormSection titulo="Pasa a amarillo">
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
        </FormPage>
    );
}
