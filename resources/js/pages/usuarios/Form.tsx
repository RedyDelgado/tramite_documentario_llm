import { useForm, usePage } from '@inertiajs/react';
import { FormField } from '@/components/forms/FormField';
import { FormSection } from '@/components/forms/FormSection';
import { FormDialog } from '@/components/layouts/FormDialog';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Switch } from '@/components/ui/Switch';
import type { Opcion, UsuarioFila } from '@/types';

type Props = { usuario: UsuarioFila | null; opcionesRol: Opcion<string>[]; dominio: string | null };

export default function UsuarioForm({ usuario, opcionesRol, dominio, onCerrar }: Props & { onCerrar: () => void }) {
    const { auth } = usePage().props;
    const esUnoMismo = usuario?.id === auth.user?.id;
    const form = useForm({
        name: usuario?.name ?? '',
        email: usuario?.email ?? '',
        rol: usuario?.rol ?? '',
        activo: usuario?.activo ?? true,
        administra_configuracion: usuario?.administra_configuracion ?? false,
    });
    const { data, setData, errors } = form;

    const enviar = () => (usuario ? form.put(`/usuarios/${usuario.id}`) : form.post('/usuarios'));

    return (
        <FormDialog
            onCerrar={onCerrar}
            titulo={usuario ? `Editar «${usuario.name}»` : 'Nuevo usuario'}
            descripcion="El usuario ingresa con su cuenta de Google; no se crean contraseñas."
            onEnviar={enviar}
            procesando={form.processing}
        >
            <FormSection titulo="Datos del usuario">
                <FormField etiqueta="Nombre" requerido error={errors.name}>
                    {(c) => <Input {...c} value={data.name} maxLength={150} autoFocus onChange={(e) => setData('name', e.target.value)} />}
                </FormField>
                <FormField
                    etiqueta="Correo institucional"
                    ayuda={dominio ? `Cuenta de Google del dominio @${dominio}.` : 'Cuenta de Google con la que ingresará.'}
                    requerido
                    error={errors.email}
                >
                    {(c) => <Input {...c} type="email" value={data.email} maxLength={150} onChange={(e) => setData('email', e.target.value)} />}
                </FormField>
                <FormField
                    etiqueta="Rol"
                    ayuda={esUnoMismo ? 'No puedes cambiar tu propio rol.' : 'Define qué ve y qué puede hacer en el sistema.'}
                    requerido
                    error={errors.rol}
                >
                    {(c) => (
                        <Select
                            {...c}
                            vacia="Elige un rol"
                            opciones={opcionesRol}
                            value={data.rol}
                            disabled={esUnoMismo}
                            onChange={(e) => setData('rol', e.target.value)}
                        />
                    )}
                </FormField>
                <FormField
                    etiqueta="Administra la configuración"
                    ayuda={
                        data.rol === 'superadmin'
                            ? 'El superadmin ya la administra por su rol.'
                            : 'Áreas, responsables, plazos, feriados, catálogos y plantillas. No incluye gestionar usuarios.'
                    }
                    error={errors.administra_configuracion}
                >
                    {(c) => (
                        <Switch
                            id={c.id}
                            aria-describedby={c['aria-describedby']}
                            checked={data.rol === 'superadmin' || data.administra_configuracion}
                            disabled={data.rol === 'superadmin'}
                            onCheckedChange={(v) => setData('administra_configuracion', v)}
                        />
                    )}
                </FormField>
                <FormField etiqueta="Activo" ayuda="Un usuario inactivo no puede ingresar; nunca se elimina." error={errors.activo}>
                    {(c) => (
                        <Switch
                            id={c.id}
                            aria-describedby={c['aria-describedby']}
                            checked={data.activo}
                            disabled={esUnoMismo}
                            onCheckedChange={(v) => setData('activo', v)}
                        />
                    )}
                </FormField>
            </FormSection>
        </FormDialog>
    );
}
