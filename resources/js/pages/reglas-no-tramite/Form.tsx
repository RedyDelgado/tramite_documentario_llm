import { useForm } from '@inertiajs/react';
import { FormField } from '@/components/forms/FormField';
import { FormSection } from '@/components/forms/FormSection';
import { FormDialog } from '@/components/layouts/FormDialog';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Switch } from '@/components/ui/Switch';
import type { Opcion, ReglaNoTramite } from '@/types';

type Props = { regla: ReglaNoTramite | null; opcionesCampo: Opcion<string>[] };

const AYUDA: Record<string, string> = {
    remitente: 'Parte del correo del remitente, p. ej. noreply.',
    dominio: 'Dominio del remitente; incluye sus subdominios.',
    asunto: 'Texto que aparece en el asunto.',
    encabezado: '«Nombre» si basta con que exista, o «Nombre: texto» si debe contenerlo. P. ej. precedence: bulk.',
};

export default function ReglaNoTramiteForm({ regla, opcionesCampo, onCerrar }: Props & { onCerrar: () => void }) {
    const form = useForm({ nombre: regla?.nombre ?? '', campo: regla?.campo ?? 'remitente', valor: regla?.valor ?? '', activa: regla?.activa ?? true });
    const { data, setData, errors } = form;

    const enviar = () => (regla ? form.put(`/reglas-no-tramite/${regla.id}`) : form.post('/reglas-no-tramite'));

    return (
        <FormDialog
            onCerrar={onCerrar}
            titulo={regla ? `Editar «${regla.nombre}»` : 'Nueva regla de correo no trámite'}
            descripcion="Sin distinguir mayúsculas. Los correos ya ingresados no se reclasifican."
            onEnviar={enviar}
            procesando={form.processing}
        >
            <FormSection titulo="Regla">
                <FormField etiqueta="Nombre" requerido error={errors.nombre} className="md:col-span-2">
                    {(c) => <Input {...c} value={data.nombre} maxLength={255} autoFocus onChange={(e) => setData('nombre', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Campo" requerido error={errors.campo}>
                    {(c) => <Select {...c} opciones={opcionesCampo} value={data.campo} onChange={(e) => setData('campo', e.target.value as ReglaNoTramite['campo'])} />}
                </FormField>
                <FormField etiqueta="Valor" ayuda={AYUDA[data.campo]} requerido error={errors.valor}>
                    {(c) => <Input {...c} value={data.valor} maxLength={255} onChange={(e) => setData('valor', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Activa" error={errors.activa}>
                    {(c) => <Switch id={c.id} aria-describedby={c['aria-describedby']} checked={data.activa} onCheckedChange={(v) => setData('activa', v)} />}
                </FormField>
            </FormSection>
        </FormDialog>
    );
}
