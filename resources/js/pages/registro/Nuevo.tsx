import { Link, useForm, useHttp } from '@inertiajs/react';
import { useState } from 'react';
import { EmisorCombobox } from '@/components/domain/EmisorCombobox';
import { FormField } from '@/components/forms/FormField';
import { FormSection } from '@/components/forms/FormSection';
import { FormDialog } from '@/components/layouts/FormDialog';
import { Checkbox } from '@/components/ui/Checkbox';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Spinner } from '@/components/ui/Spinner';
import { Textarea } from '@/components/ui/Textarea';
import type { Opcion } from '@/types';

type Props = { opcionesEmisor: Opcion<number>[]; opcionesTipoDocumento: Opcion<number>[]; opcionesUbicacion: Opcion<number>[]; ultimoNumeroEnPapel: number };

/** RegistroFisicoService::prellenar. */
type Prellenado = {
    sha256: string;
    nombre: string;
    paginas: number | null;
    campos: Partial<{
        tipo_documento_id: number | null;
        numero_documento_original: string | null;
        fecha_documento: string | null;
        asunto: string | null;
        emisor_id: number | null;
        folios: number | null;
    }>;
    mismo_archivo: { id: number; numero: string | null } | null;
};

export default function RegistroNuevo({ opcionesEmisor, opcionesTipoDocumento, opcionesUbicacion, ultimoNumeroEnPapel, onCerrar }: Props & { onCerrar: () => void }) {
    const [escaneo, setEscaneo] = useState<Prellenado | null>(null);
    const [errorEmisor, setErrorEmisor] = useState<string>();
    const subida = useHttp<{ archivo: File | null }, Prellenado>('post', '/registro/prellenar', { archivo: null });
    const form = useForm({
        sha256: '',
        asunto: '',
        emisor_id: null as number | null,
        tipo_documento_id: '',
        numero_documento: '',
        fecha_documento: '',
        folios: '',
        motivo_folios: '',
        requiere_respuesta: true,
        ubicacion_fisica_id: '',
        confirmar_duplicado: false,
        en_curso: false,
        numero_papel: '',
        fecha_ingreso: '',
    });
    const { data, setData, errors } = form;
    // Errores que no son de un campo: duplicados (7.3.5) y escaneo vencido.
    const extra = errors as Record<string, string | undefined>;

    const subir = (archivo: File | undefined) => {
        if (!archivo) return;
        subida.transform(() => ({ archivo }));
        subida
            .post('/registro/prellenar', {
                onSuccess: (r) => {
                    setEscaneo(r);
                    const c = r.campos;
                    form.setData((d) => ({
                        ...d,
                        sha256: r.sha256,
                        asunto: c.asunto ?? d.asunto,
                        emisor_id: c.emisor_id ?? d.emisor_id,
                        tipo_documento_id: c.tipo_documento_id ? String(c.tipo_documento_id) : d.tipo_documento_id,
                        numero_documento: c.numero_documento_original ?? d.numero_documento,
                        fecha_documento: c.fecha_documento ?? d.fecha_documento,
                        folios: c.folios ? String(c.folios) : d.folios,
                        confirmar_duplicado: false,
                    }));
                },
            })
            .catch(() => undefined);
    };

    const enviar = () => {
        form.transform((d) => ({
            ...d,
            tipo_documento_id: Number(d.tipo_documento_id) || null,
            folios: Number(d.folios) || null,
            motivo_folios: d.motivo_folios || null,
            ubicacion_fisica_id: Number(d.ubicacion_fisica_id) || null,
            numero_papel: Number(d.numero_papel) || null,
            fecha_ingreso: d.fecha_ingreso || null,
        }));
        form.post('/registro');
    };

    const foliosCambiados = escaneo?.paginas != null && data.folios !== '' && Number(data.folios) !== escaneo.paginas;

    return (
        <FormDialog
            onCerrar={onCerrar}
            tamano="xl"
            titulo="Registrar documento en papel"
            descripcion="Sube el escaneo: el sistema propone los datos y tú los confirmas. El escaneo es una copia digital; el original en papel se conserva siempre."
            onEnviar={enviar}
            procesando={form.processing}
            textoGuardar="Registrar"
        >
            <FormSection titulo="Escaneo" descripcion="PDF o imagen. Si no tiene texto, se lee con OCR.">
                <FormField etiqueta="Archivo" requerido error={subida.errors.archivo ?? extra.archivo ?? errors.sha256} className="md:col-span-2">
                    {(c) => (
                        <Input
                            {...c}
                            type="file"
                            accept="application/pdf,image/png,image/jpeg,image/tiff,image/webp"
                            className="py-1"
                            onChange={(e) => subir(e.target.files?.[0])}
                        />
                    )}
                </FormField>
                {subida.processing && (
                    <p className="flex items-center gap-2 text-base text-fg-muted md:col-span-2">
                        <Spinner /> Leyendo el documento…
                    </p>
                )}
                {escaneo && (
                    <p className="text-base text-fg-muted md:col-span-2">
                        {escaneo.nombre} · {escaneo.paginas ?? '?'} {escaneo.paginas === 1 ? 'página' : 'páginas'}
                        {escaneo.mismo_archivo && (
                            <>
                                {' '}· Este mismo archivo ya está en{' '}
                                <Link href={`/expedientes/${escaneo.mismo_archivo.id}`} className="text-primary-700 underline">
                                    {escaneo.mismo_archivo.numero ?? 'otro expediente'}
                                </Link>
                            </>
                        )}
                    </p>
                )}
            </FormSection>

            <FormSection titulo="Documento" descripcion="Revisa lo propuesto; corrige solo lo que no coincida con el papel.">
                <FormField etiqueta="Emisor" requerido error={errorEmisor ?? errors.emisor_id}>
                    {(c) => <EmisorCombobox {...c} opciones={opcionesEmisor} value={data.emisor_id} onChange={(v) => setData('emisor_id', v)} onError={setErrorEmisor} />}
                </FormField>
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
                <FormField
                    etiqueta="N° de documento"
                    ayuda="Tal como figura; se normaliza al guardar."
                    requerido
                    error={errors.numero_documento}
                    className="md:col-span-2"
                >
                    {(c) => <Input {...c} value={data.numero_documento} maxLength={150} onChange={(e) => setData('numero_documento', e.target.value)} />}
                </FormField>
                {extra.duplicado_id && (
                    <p className="text-base md:col-span-2">
                        <Link href={`/expedientes/${extra.duplicado_id}`} className="text-primary-700 underline">
                            Abrir el expediente ya registrado
                        </Link>
                    </p>
                )}
                <FormField etiqueta="Fecha del documento" requerido error={errors.fecha_documento}>
                    {(c) => <Input {...c} type="date" value={data.fecha_documento} onChange={(e) => setData('fecha_documento', e.target.value)} />}
                </FormField>
                <FormField etiqueta="Folios" ayuda="Las páginas del escaneo." requerido error={errors.folios}>
                    {(c) => <Input {...c} type="number" min={1} max={5000} value={data.folios} onChange={(e) => setData('folios', e.target.value)} />}
                </FormField>
                {foliosCambiados && (
                    <FormField etiqueta="Motivo del cambio de folios" requerido error={errors.motivo_folios} className="md:col-span-2">
                        {(c) => <Input {...c} value={data.motivo_folios} maxLength={300} onChange={(e) => setData('motivo_folios', e.target.value)} />}
                    </FormField>
                )}
                <FormField etiqueta="Asunto" requerido error={errors.asunto} className="md:col-span-2">
                    {(c) => <Textarea {...c} value={data.asunto} maxLength={500} onChange={(e) => setData('asunto', e.target.value)} />}
                </FormField>
                <Checkbox
                    etiqueta="Requiere respuesta"
                    checked={data.requiere_respuesta}
                    onChange={(e) => setData('requiere_respuesta', e.target.checked)}
                />
            </FormSection>

            {ultimoNumeroEnPapel > 0 && (
                <FormSection titulo="Trámite en curso" descripcion="Solo para lo que ya estaba en el registro en papel: conserva su número y su fecha de ingreso.">
                    <Checkbox
                        etiqueta="Ya estaba en el registro en papel"
                        checked={data.en_curso}
                        onChange={(e) => setData('en_curso', e.target.checked)}
                        className="md:col-span-2"
                    />
                    {data.en_curso && (
                        <>
                            <FormField etiqueta="N° en el registro en papel" ayuda={`Del 1 al ${ultimoNumeroEnPapel}.`} requerido error={errors.numero_papel}>
                                {(c) => <Input {...c} type="number" min={1} max={ultimoNumeroEnPapel} value={data.numero_papel} onChange={(e) => setData('numero_papel', e.target.value)} />}
                            </FormField>
                            <FormField etiqueta="Fecha de ingreso" ayuda="La del registro en papel: de ella salen el plazo y el semáforo." requerido error={errors.fecha_ingreso}>
                                {(c) => <Input {...c} type="date" value={data.fecha_ingreso} onChange={(e) => setData('fecha_ingreso', e.target.value)} />}
                            </FormField>
                        </>
                    )}
                </FormSection>
            )}

            <FormSection titulo="Original en papel" descripcion="Se conserva siempre; tú quedas como su custodio al registrarlo.">
                <FormField etiqueta="Ubicación" ayuda="Archivador, caja o estante." error={errors.ubicacion_fisica_id}>
                    {(c) => (
                        <Select
                            {...c}
                            vacia="Sin asignar todavía"
                            opciones={opcionesUbicacion}
                            value={data.ubicacion_fisica_id}
                            onChange={(e) => setData('ubicacion_fisica_id', e.target.value)}
                        />
                    )}
                </FormField>
            </FormSection>

            {extra.duplicado && (
                <FormSection titulo="Posible duplicado">
                    <p className="text-base text-danger md:col-span-2">{extra.duplicado}</p>
                    <Checkbox
                        etiqueta="Es otro documento: registrarlo de todos modos"
                        checked={data.confirmar_duplicado}
                        onChange={(e) => setData('confirmar_duplicado', e.target.checked)}
                    />
                </FormSection>
            )}
        </FormDialog>
    );
}
