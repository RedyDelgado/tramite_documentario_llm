import { useForm } from '@inertiajs/react';
import { FormField } from '@/components/forms/FormField';
import { FormSection } from '@/components/forms/FormSection';
import { FormPage } from '@/components/layouts/FormPage';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Switch } from '@/components/ui/Switch';
import { Textarea } from '@/components/ui/Textarea';
import type { Opcion, TipoTramite } from '@/types';

type Props = { tipo: TipoTramite | null; opcionesDias: Opcion<string>[]; opcionesCierre: Opcion<string>[] };

export default function TipoTramiteForm({ tipo, opcionesDias, opcionesCierre }: Props) {
    const form = useForm({
        nombre: tipo?.nombre ?? '',
        descripcion: tipo?.descripcion ?? '',
        plazo_dias: tipo?.plazo_dias ? String(tipo.plazo_dias) : '',
        tipo_dias: (tipo?.tipo_dias ?? 'habiles') as string,
        aprueba_cierre: (tipo?.aprueba_cierre ?? '') as string,
        activo: tipo?.activo ?? true,
    });
    const { data, setData, errors } = form;

    const enviar = () => {
        form.transform((d) => ({ ...d, plazo_dias: d.plazo_dias ? Number(d.plazo_dias) : null, aprueba_cierre: d.aprueba_cierre || null }));
        if (tipo) {
            form.put(`/tipos-tramite/${tipo.id}`);
        } else {
            form.post('/tipos-tramite');
        }
    };

    return (
        <FormPage
            titulo={tipo ? `Editar «${tipo.nombre}»` : 'Nuevo tipo de trámite'}
            descripcion="Los cambios surten efecto de inmediato; los expedientes ya ingresados conservan el plazo que se les aplicó."
            volverA="/tipos-tramite"
            onEnviar={enviar}
            procesando={form.processing}
        >
            <FormSection titulo="Datos del tipo">
                <FormField etiqueta="Nombre" requerido error={errors.nombre} className="md:col-span-2">
                    {(c) => <Input {...c} value={data.nombre} maxLength={150} autoFocus onChange={(e) => setData('nombre', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Descripción" error={errors.descripcion} className="md:col-span-2">
                    {(c) => <Textarea {...c} value={data.descripcion} maxLength={2000} onChange={(e) => setData('descripcion', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Activo" ayuda="Un tipo inactivo no se asigna a nuevos expedientes." error={errors.activo}>
                    {(c) => <Switch id={c.id} aria-describedby={c['aria-describedby']} checked={data.activo} onCheckedChange={(v) => setData('activo', v)} />}
                </FormField>
            </FormSection>

            <FormSection titulo="Plazo y cierre" descripcion="Cada área puede tener un plazo distinto en «Plazos por área».">
                <FormField etiqueta="Plazo en días" ayuda="Vacío: sin vencimiento (p. ej. comunicaciones para conocimiento)." error={errors.plazo_dias}>
                    {(c) => <Input {...c} type="number" min={1} max={365} value={data.plazo_dias} onChange={(e) => setData('plazo_dias', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Se cuentan" ayuda="Los hábiles excluyen fines de semana y feriados." requerido error={errors.tipo_dias}>
                    {(c) => (
                        <Select
                            {...c}
                            opciones={opcionesDias}
                            value={data.tipo_dias}
                            onChange={(e) => setData('tipo_dias', e.target.value)}
                        />
                    )}
                </FormField>
                <FormField etiqueta="Aprueba el cierre" ayuda="Quién debe aprobar para cerrar un trámite de este tipo." error={errors.aprueba_cierre}>
                    {(c) => (
                        <Select
                            {...c}
                            vacia="No requiere aprobación"
                            opciones={opcionesCierre}
                            value={data.aprueba_cierre}
                            onChange={(e) => setData('aprueba_cierre', e.target.value)}
                        />
                    )}
                </FormField>
            </FormSection>
        </FormPage>
    );
}
