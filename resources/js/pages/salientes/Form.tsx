import { useForm, useHttp } from '@inertiajs/react';
import { FormField } from '@/components/forms/FormField';
import { FormSection } from '@/components/forms/FormSection';
import { FormDialog } from '@/components/layouts/FormDialog';
import { Checkbox } from '@/components/ui/Checkbox';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { TagsInput } from '@/components/ui/TagsInput';
import { Textarea } from '@/components/ui/Textarea';
import type { Opcion } from '@/types';

type Destinatario = { email: string; nombre: string | null };

type SalienteEditable = {
    id: number;
    expediente_id: number | null;
    plantilla_id: number | null;
    tipo_documento_id: number;
    area_id: number;
    asunto: string;
    cuerpo: string;
    destinatarios: Destinatario[];
    es_respuesta: boolean;
    requiere_respuesta: boolean;
    plazo_respuesta_dias: number | null;
    esperar_firma: boolean;
    observacion: string | null;
};

type Props = {
    saliente: SalienteEditable | null;
    expediente: { id: number; codigo: string | null; asunto: string; remitente: Destinatario; area_id: number | null } | null;
    opcionesTipo: Opcion<number>[];
    opcionesArea: Opcion<number>[];
    opcionesPlantilla: Opcion<number>[];
};

export default function SalienteForm({ saliente, expediente, opcionesTipo, opcionesArea, opcionesPlantilla, onCerrar }: Props & { onCerrar: () => void }) {
    // Los nombres conocidos (el remitente del expediente) acompañan a su correo.
    const nombres = new Map((saliente?.destinatarios ?? (expediente?.remitente.email ? [expediente.remitente] : [])).map((d) => [d.email, d.nombre]));
    const form = useForm({
        expediente_id: saliente?.expediente_id ?? expediente?.id ?? null,
        plantilla_id: saliente?.plantilla_id ? String(saliente.plantilla_id) : '',
        tipo_documento_id: saliente ? String(saliente.tipo_documento_id) : '',
        area_id: String(saliente?.area_id ?? expediente?.area_id ?? (opcionesArea.length === 1 ? opcionesArea[0].value : '')),
        asunto: saliente?.asunto ?? (expediente ? `Respuesta: ${expediente.asunto}`.slice(0, 300) : ''),
        cuerpo: saliente?.cuerpo ?? '',
        destinatarios: [...nombres.keys()],
        es_respuesta: saliente?.es_respuesta ?? Boolean(expediente),
        requiere_respuesta: saliente?.requiere_respuesta ?? false,
        plazo_respuesta_dias: saliente?.plazo_respuesta_dias ? String(saliente.plazo_respuesta_dias) : '',
        esperar_firma: saliente?.esperar_firma ?? false,
    });
    const { data, setData, errors } = form;
    const errorDestinatarios = errors.destinatarios ?? Object.entries(errors).find(([k]) => k.startsWith('destinatarios.'))?.[1];
    const plantilla = useHttp<Record<string, never>, { tipo_documento_id: number; asunto: string; cuerpo: string }>('get', '', {});

    const usarPlantilla = (id: string) => {
        setData('plantilla_id', id);
        if (!id) return;
        const params = new URLSearchParams({ ...(data.expediente_id ? { expediente: String(data.expediente_id) } : {}), area: data.area_id });
        plantilla
            .get(`/salientes/plantilla/${id}?${params}`, {
                onSuccess: (r) => form.setData((d) => ({ ...d, plantilla_id: id, tipo_documento_id: String(r.tipo_documento_id), asunto: r.asunto, cuerpo: r.cuerpo })),
            })
            .catch(() => undefined);
    };

    const enviar = () => {
        form.transform((d) => ({
            ...d,
            plantilla_id: Number(d.plantilla_id) || null,
            tipo_documento_id: Number(d.tipo_documento_id) || null,
            area_id: Number(d.area_id) || null,
            destinatarios: d.destinatarios.map((email) => ({ email, nombre: nombres.get(email) ?? null })),
            plazo_respuesta_dias: d.requiere_respuesta ? Number(d.plazo_respuesta_dias) || null : null,
        }));
        if (saliente) {
            form.put(`/salientes/${saliente.id}`);
        } else {
            form.post('/salientes');
        }
    };

    return (
        <FormDialog
            onCerrar={onCerrar}
            tamano="xl"
            titulo={saliente ? 'Editar borrador' : expediente?.codigo ? `Redactar documento · ${expediente.codigo}` : 'Redactar documento'}
            descripcion={
                saliente?.observacion
                    ? `Observación de la revisión: ${saliente.observacion}`
                    : 'Se guarda como borrador. Recibe número y sale solo después de la aprobación.'
            }
            onEnviar={enviar}
            procesando={form.processing}
            textoGuardar="Guardar borrador"
        >
            <FormSection titulo="Documento">
                <FormField etiqueta="Plantilla" ayuda="Llena asunto y cuerpo con los datos del expediente." error={errors.plantilla_id}>
                    {(c) => <Select {...c} vacia="Sin plantilla" opciones={opcionesPlantilla} value={data.plantilla_id} onChange={(e) => usarPlantilla(e.target.value)} />}
                </FormField>
                <FormField etiqueta="Tipo de documento" requerido error={errors.tipo_documento_id}>
                    {(c) => (
                        <Select {...c} vacia="Elige el tipo" opciones={opcionesTipo} value={data.tipo_documento_id} onChange={(e) => setData('tipo_documento_id', e.target.value)} />
                    )}
                </FormField>
                <FormField etiqueta="Área que emite" requerido error={errors.area_id}>
                    {(c) => <Select {...c} vacia="Elige el área" opciones={opcionesArea} value={data.area_id} onChange={(e) => setData('area_id', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Destinatarios" ayuda="Correos; cada uno recibe un correo individual." requerido error={errorDestinatarios}>
                    {(c) => <TagsInput {...c} valor={data.destinatarios} placeholder="correo@ejemplo.edu.pe" onCambiar={(v) => setData('destinatarios', v)} />}
                </FormField>
                <FormField etiqueta="Asunto" requerido error={errors.asunto} className="md:col-span-2">
                    {(c) => <Input {...c} value={data.asunto} maxLength={300} onChange={(e) => setData('asunto', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Cuerpo" ayuda="Una línea en blanco separa párrafos." requerido error={errors.cuerpo} className="md:col-span-2">
                    {(c) => <Textarea {...c} rows={14} value={data.cuerpo} maxLength={20000} onChange={(e) => setData('cuerpo', e.target.value)} />}
                </FormField>
            </FormSection>

            <FormSection titulo="Envío">
                {data.expediente_id && (
                    <Checkbox
                        etiqueta="Es la respuesta al expediente: al enviarse lo deja atendido"
                        checked={data.es_respuesta}
                        onChange={(e) => setData('es_respuesta', e.target.checked)}
                    />
                )}
                <Checkbox
                    etiqueta="Esperar el PDF firmado antes de enviar"
                    checked={data.esperar_firma}
                    onChange={(e) => setData('esperar_firma', e.target.checked)}
                />
                <Checkbox
                    etiqueta="Exige respuesta del destinatario"
                    checked={data.requiere_respuesta}
                    onChange={(e) => setData('requiere_respuesta', e.target.checked)}
                />
                {data.requiere_respuesta && (
                    <FormField etiqueta="Plazo de respuesta (días hábiles)" requerido error={errors.plazo_respuesta_dias}>
                        {(c) => (
                            <Input
                                {...c}
                                type="number"
                                min={1}
                                max={365}
                                value={data.plazo_respuesta_dias}
                                onChange={(e) => setData('plazo_respuesta_dias', e.target.value)}
                            />
                        )}
                    </FormField>
                )}
            </FormSection>
        </FormDialog>
    );
}
