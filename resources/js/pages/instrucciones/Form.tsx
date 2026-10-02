import { useForm } from '@inertiajs/react';
import { FormField } from '@/components/forms/FormField';
import { FormSection } from '@/components/forms/FormSection';
import { FormPage } from '@/components/layouts/FormPage';
import { Input } from '@/components/ui/Input';
import { Switch } from '@/components/ui/Switch';
import type { InstruccionFrecuente } from '@/types';

export default function InstruccionForm({ instruccion }: { instruccion: InstruccionFrecuente | null }) {
    const form = useForm({ texto: instruccion?.texto ?? '', orden: String(instruccion?.orden ?? 0), activa: instruccion?.activa ?? true });
    const { data, setData, errors } = form;

    const enviar = () => {
        form.transform((d) => ({ ...d, orden: Number(d.orden) }));
        if (instruccion) {
            form.put(`/instrucciones/${instruccion.id}`);
        } else {
            form.post('/instrucciones');
        }
    };

    return (
        <FormPage titulo={instruccion ? 'Editar instrucción' : 'Nueva instrucción'} volverA="/instrucciones" onEnviar={enviar} procesando={form.processing}>
            <FormSection titulo="Instrucción">
                <FormField etiqueta="Texto" requerido error={errors.texto} className="md:col-span-2">
                    {(c) => <Input {...c} value={data.texto} maxLength={200} autoFocus onChange={(e) => setData('texto', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Orden" ayuda="Posición en la lista al derivar." requerido error={errors.orden}>
                    {(c) => <Input {...c} type="number" min={0} max={9999} value={data.orden} onChange={(e) => setData('orden', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Activa" error={errors.activa}>
                    {(c) => <Switch id={c.id} aria-describedby={c['aria-describedby']} checked={data.activa} onCheckedChange={(v) => setData('activa', v)} />}
                </FormField>
            </FormSection>
        </FormPage>
    );
}
