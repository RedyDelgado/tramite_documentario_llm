import { useForm } from '@inertiajs/react';
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
    tipo_documento_id: number;
    area_id: number;
    asunto: string;
    cuerpo: string | null;
    destinatarios: Destinatario[];
    es_respuesta: boolean;
    requiere_respuesta: boolean;
    plazo_respuesta_dias: number | null;
    // Nombre del borrador ya subido.
    borrador: string | null;
    observacion: string | null;
};

type Props = {
    saliente: SalienteEditable | null;
    expediente: { id: number; codigo: string | null; asunto: string; remitente: Destinatario; area_id: number | null } | null;
    opcionesTipo: Opcion<number>[];
    opcionesArea: Opcion<number>[];
};

const WORD_O_PDF = '.doc,.docx,.pdf,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document';

/** El documento se redacta en Word: aquí se sube el borrador con sus datos de envío; el número llega al aprobarse. */
export default function SalienteForm({ saliente, expediente, opcionesTipo, opcionesArea, onCerrar }: Props & { onCerrar: () => void }) {
    // Los nombres conocidos (el remitente del expediente) acompañan a su correo.
    const nombres = new Map((saliente?.destinatarios ?? (expediente?.remitente.email ? [expediente.remitente] : [])).map((d) => [d.email, d.nombre]));
    const form = useForm({
        expediente_id: saliente?.expediente_id ?? expediente?.id ?? null,
        tipo_documento_id: saliente ? String(saliente.tipo_documento_id) : '',
        area_id: String(saliente?.area_id ?? expediente?.area_id ?? (opcionesArea.length === 1 ? opcionesArea[0].value : '')),
        asunto: saliente?.asunto ?? (expediente ? `Respuesta: ${expediente.asunto}`.slice(0, 300) : ''),
        cuerpo: saliente?.cuerpo ?? '',
        archivo: null as File | null,
        destinatarios: [...nombres.keys()],
        es_respuesta: saliente?.es_respuesta ?? Boolean(expediente),
        requiere_respuesta: saliente?.requiere_respuesta ?? false,
        plazo_respuesta_dias: saliente?.plazo_respuesta_dias ? String(saliente.plazo_respuesta_dias) : '',
    });
    const { data, setData, errors } = form;
    const errorDestinatarios = errors.destinatarios ?? Object.entries(errors).find(([k]) => k.startsWith('destinatarios.'))?.[1];

    const enviar = () => {
        form.transform((d) => ({
            ...d,
            // Con archivo va como formulario multipart: la edición viaja por POST con _method.
            ...(saliente ? { _method: 'put' } : {}),
            tipo_documento_id: Number(d.tipo_documento_id) || null,
            area_id: Number(d.area_id) || null,
            destinatarios: d.destinatarios.map((email) => ({ email, nombre: nombres.get(email) ?? null })),
            plazo_respuesta_dias: d.requiere_respuesta ? Number(d.plazo_respuesta_dias) || null : null,
        }));
        form.post(saliente ? `/salientes/${saliente.id}` : '/salientes', { forceFormData: true });
    };

    return (
        <FormDialog
            onCerrar={onCerrar}
            tamano="lg"
            titulo={saliente ? 'Editar borrador' : expediente?.codigo ? `Emitir documento · ${expediente.codigo}` : 'Emitir documento'}
            descripcion={
                saliente?.observacion
                    ? `Observación de la revisión: ${saliente.observacion}`
                    : 'Sube el borrador hecho en Word. Al aprobarse recibe su número; pones ese número en el Word y subes el documento final, que es el que se envía.'
            }
            onEnviar={enviar}
            procesando={form.processing}
            textoGuardar="Guardar borrador"
        >
            <FormSection titulo="Documento">
                <FormField
                    etiqueta="Borrador (Word o PDF)"
                    ayuda={saliente?.borrador ? `Actual: ${saliente.borrador}. Elige otro solo si lo reemplazas.` : 'Lo que revisará quien aprueba.'}
                    requerido={!saliente}
                    error={errors.archivo}
                    className="md:col-span-2"
                >
                    {(c) => <Input {...c} type="file" accept={WORD_O_PDF} className="py-0.5 pl-1" onChange={(e) => setData('archivo', e.target.files?.[0] ?? null)} />}
                </FormField>
                <FormField etiqueta="Tipo de documento" requerido error={errors.tipo_documento_id}>
                    {(c) => (
                        <Select {...c} vacia="Elige el tipo" opciones={opcionesTipo} value={data.tipo_documento_id} onChange={(e) => setData('tipo_documento_id', e.target.value)} />
                    )}
                </FormField>
                <FormField etiqueta="Área que emite" requerido error={errors.area_id}>
                    {(c) => <Select {...c} vacia="Elige el área" opciones={opcionesArea} value={data.area_id} onChange={(e) => setData('area_id', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Asunto" requerido error={errors.asunto} className="md:col-span-2">
                    {(c) => <Input {...c} value={data.asunto} maxLength={300} onChange={(e) => setData('asunto', e.target.value)} />}
                </FormField>
            </FormSection>

            <FormSection titulo="Envío por correo">
                <FormField etiqueta="Destinatarios" ayuda="Correos; cada uno recibe un correo individual con el documento adjunto." requerido error={errorDestinatarios} className="md:col-span-2">
                    {(c) => <TagsInput {...c} valor={data.destinatarios} placeholder="correo@ejemplo.edu.pe" onCambiar={(v) => setData('destinatarios', v)} />}
                </FormField>
                <FormField etiqueta="Mensaje del correo" ayuda="Opcional: el texto que acompaña al adjunto." error={errors.cuerpo} className="md:col-span-2">
                    {(c) => <Textarea {...c} rows={3} value={data.cuerpo} maxLength={5000} onChange={(e) => setData('cuerpo', e.target.value)} />}
                </FormField>
                {data.expediente_id && (
                    <Checkbox
                        etiqueta="Es la respuesta al expediente: al enviarse lo deja atendido"
                        checked={data.es_respuesta}
                        onChange={(e) => setData('es_respuesta', e.target.checked)}
                    />
                )}
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
