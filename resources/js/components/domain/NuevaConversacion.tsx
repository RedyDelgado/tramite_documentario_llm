import { useForm } from '@inertiajs/react';
import { FormField } from '@/components/forms/FormField';
import { FormDialog } from '@/components/layouts/FormDialog';
import { ComboboxMultiple } from '@/components/ui/ComboboxMultiple';
import { Input } from '@/components/ui/Input';
import type { Opcion } from '@/types';

/** Con una persona abre la conversación que ya tenían; con varias crea un grupo, que puede llevar nombre. */
export function NuevaConversacion({ usuarios, onCerrar }: { usuarios: Opcion<number>[]; onCerrar: () => void }) {
    const form = useForm<{ usuarios: number[]; nombre: string }>({ usuarios: [], nombre: '' });
    const grupo = form.data.usuarios.length > 1;

    return (
        <FormDialog
            titulo="Nueva conversación"
            descripcion="Elige a una persona para hablar a solas, o a varias para un grupo."
            onCerrar={onCerrar}
            onEnviar={() => form.post('/mensajes', { onSuccess: onCerrar })}
            procesando={form.processing}
            textoGuardar={grupo ? 'Crear grupo' : 'Abrir conversación'}
            listo={form.data.usuarios.length > 0}
            tamano="md"
        >
            <FormField etiqueta="Con quién" requerido error={form.errors.usuarios}>
                {(control) => (
                    <ComboboxMultiple {...control} opciones={usuarios} valor={form.data.usuarios} onCambiar={(v) => form.setData('usuarios', v)} placeholder="Busca por nombre o correo" />
                )}
            </FormField>
            {grupo && (
                <FormField etiqueta="Nombre del grupo" ayuda="Opcional. Sin nombre, el grupo se reconoce por quiénes están." error={form.errors.nombre}>
                    {(control) => <Input {...control} maxLength={80} placeholder="Ej.: Mesa de partes" value={form.data.nombre} onChange={(e) => form.setData('nombre', e.target.value)} />}
                </FormField>
            )}
        </FormDialog>
    );
}
