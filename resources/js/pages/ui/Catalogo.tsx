import { Add20Regular, Delete20Regular, Edit20Regular, Search20Regular } from '@fluentui/react-icons';
import { useEffect, useState, type ReactNode } from 'react';
import { DataTable, type Columna, type Orden } from '@/components/data/DataTable';
import { Pagination } from '@/components/data/Pagination';
import { CommandBar } from '@/components/data/CommandBar';
import { ActivoBadge } from '@/components/domain/ActivoBadge';
import { SemaforoBadge, type Semaforo } from '@/components/domain/SemaforoBadge';
import { FormField } from '@/components/forms/FormField';
import { FormSection } from '@/components/forms/FormSection';
import { AppShell } from '@/components/layouts/AppShell';
import { PageHeader } from '@/components/layouts/PageHeader';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Checkbox } from '@/components/ui/Checkbox';
import { ConfirmDialog } from '@/components/ui/ConfirmDialog';
import { Dialog } from '@/components/ui/Dialog';
import { Drawer } from '@/components/ui/Drawer';
import { EmptyState } from '@/components/ui/EmptyState';
import { IconButton } from '@/components/ui/IconButton';
import { Input } from '@/components/ui/Input';
import { Select } from '@/components/ui/Select';
import { Skeleton } from '@/components/ui/Skeleton';
import { Spinner } from '@/components/ui/Spinner';
import { Switch } from '@/components/ui/Switch';
import { TagsInput } from '@/components/ui/TagsInput';
import { Textarea } from '@/components/ui/Textarea';
import { avisar } from '@/components/ui/Toaster';

const TOKENS = {
    Marca: ['primary-50', 'primary-100', 'primary-200', 'primary-300', 'primary-400', 'primary-500', 'primary-600', 'primary-700', 'primary-800', 'primary-900', 'primary-950'],
    Neutros: ['bg-app', 'surface', 'surface-subtle', 'border', 'border-strong', 'text-primary', 'text-secondary', 'text-disabled'],
    Estados: ['status-ok', 'status-ok-bg', 'status-warn', 'status-warn-bg', 'status-danger', 'status-danger-bg', 'status-neutral', 'status-neutral-bg'],
};

function Muestra({ token }: { token: string }) {
    const [valor, setValor] = useState('');
    useEffect(() => setValor(getComputedStyle(document.documentElement).getPropertyValue(`--${token}`).trim()), [token]);

    return (
        <div className="w-28">
            <div className="h-12 rounded-control border border-border" style={{ background: `var(--${token})` }} />
            <p className="mt-1 text-sm font-semibold text-fg">{token}</p>
            <p className="text-sm text-fg-muted uppercase">{valor}</p>
        </div>
    );
}

function Bloque({ titulo, children }: { titulo: string; children: ReactNode }) {
    return (
        <Card titulo={titulo}>
            <div className="flex flex-col gap-4">{children}</div>
        </Card>
    );
}

function Fila({ etiqueta, children }: { etiqueta: string; children: ReactNode }) {
    return (
        <div className="flex items-start gap-3">
            <span className="w-28 shrink-0 pt-1.5 text-sm font-semibold text-fg-muted">{etiqueta}</span>
            <div className="flex flex-1 flex-wrap items-center gap-3">{children}</div>
        </div>
    );
}

type Ejemplo = { id: number; asunto: string; area: string; semaforo: Semaforo; activo: boolean };

const EJEMPLOS: Ejemplo[] = [
    { id: 38, asunto: 'Invitación a ceremonia de aniversario', area: 'Dirección', semaforo: 'verde', activo: true },
    { id: 39, asunto: 'Requerimiento de información de docentes', area: 'Escuela Profesional', semaforo: 'amarillo', activo: true },
    { id: 40, asunto: 'Solicitud de equipos para laboratorio', area: 'Laboratorio de cómputo', semaforo: 'rojo', activo: false },
    { id: 41, asunto: 'Oficio circular sobre encuesta de sostenibilidad', area: 'Sin asignar', semaforo: 'gris', activo: true },
];

const columnas: Columna<Ejemplo>[] = [
    { clave: 'id', titulo: 'N° registro', ordenable: true, ancho: '8rem', celda: (e) => `N°${String(e.id).padStart(5, '0')}` },
    { clave: 'asunto', titulo: 'Asunto', ordenable: true, celda: (e) => e.asunto },
    { clave: 'area', titulo: 'Área', celda: (e) => e.area },
    { clave: 'semaforo', titulo: 'Semáforo', ancho: '9rem', celda: (e) => <SemaforoBadge estado={e.semaforo} /> },
    { clave: 'activo', titulo: 'Estado', ancho: '8rem', celda: (e) => <ActivoBadge activo={e.activo} /> },
];

export default function Catalogo() {
    const [dialogo, setDialogo] = useState(false);
    const [confirmar, setConfirmar] = useState(false);
    const [drawer, setDrawer] = useState(false);
    const [orden, setOrden] = useState<Orden>({ clave: 'id', dir: 'asc' });
    const [elegida, setElegida] = useState<number | null>(39);
    const [etiquetas, setEtiquetas] = useState(['oficio', 'convenio']);
    const [interruptor, setInterruptor] = useState(true);

    const filas = [...EJEMPLOS].sort((a, b) => {
        const va = a[orden.clave as keyof Ejemplo];
        const vb = b[orden.clave as keyof Ejemplo];
        const r = va < vb ? -1 : va > vb ? 1 : 0;
        return orden.dir === 'asc' ? r : -r;
    });

    return (
        <AppShell>
            <PageHeader titulo="Catálogo de componentes" descripcion="Referencia del sistema de diseño (5.2 y 5.3). Solo en entorno local." />
            <div className="flex flex-col gap-4">
                <Bloque titulo="Tokens de color">
                    {Object.entries(TOKENS).map(([grupo, lista]) => (
                        <Fila key={grupo} etiqueta={grupo}>
                            {lista.map((t) => (
                                <Muestra key={t} token={t} />
                            ))}
                        </Fila>
                    ))}
                </Bloque>

                <Bloque titulo="Tipografía">
                    <p className="text-xl font-semibold">28 px · Título de página</p>
                    <p className="text-lg font-semibold">20 px · Título de sección</p>
                    <p className="text-md">16 px · Texto destacado</p>
                    <p className="text-base">14 px · Texto base del sistema</p>
                    <p className="text-sm text-fg-muted">12 px · Etiquetas y ayudas</p>
                </Bloque>

                <Bloque titulo="Botones">
                    <Fila etiqueta="Variantes">
                        <Button variante="primario" icono={<Add20Regular />}>
                            Primario
                        </Button>
                        <Button>Secundario</Button>
                        <Button variante="sutil">Sutil</Button>
                        <Button variante="peligro" icono={<Delete20Regular />}>
                            Peligro
                        </Button>
                    </Fila>
                    <Fila etiqueta="Pequeños">
                        <Button tamano="sm" variante="primario">
                            Primario
                        </Button>
                        <Button tamano="sm">Secundario</Button>
                        <Button tamano="sm" variante="sutil">
                            Sutil
                        </Button>
                    </Fila>
                    <Fila etiqueta="Estados">
                        <Button variante="primario" cargando>
                            Guardando
                        </Button>
                        <Button variante="primario" disabled>
                            Deshabilitado
                        </Button>
                        <Button disabled>Deshabilitado</Button>
                    </Fila>
                    <Fila etiqueta="Solo icono">
                        <IconButton icono={<Edit20Regular />} etiqueta="Editar" />
                        <IconButton icono={<Delete20Regular />} etiqueta="Eliminar" tamano="sm" />
                    </Fila>
                </Bloque>

                <FormSection titulo="Campos de formulario" descripcion="Cada control se cablea con FormField: etiqueta, ayuda y error accesibles.">
                    <FormField etiqueta="Texto" requerido ayuda="Ayuda bajo el campo.">
                        {(c) => <Input {...c} placeholder="Escribe aquí" />}
                    </FormField>
                    <FormField etiqueta="Con error" error="El nombre ya está registrado.">
                        {(c) => <Input {...c} defaultValue="Dirección" />}
                    </FormField>
                    <FormField etiqueta="Con icono">
                        {(c) => <Input {...c} iconoInicio={<Search20Regular />} placeholder="Buscar" />}
                    </FormField>
                    <FormField etiqueta="Deshabilitado">
                        {(c) => <Input {...c} disabled defaultValue="Asignado por el sistema" />}
                    </FormField>
                    <FormField etiqueta="Selección">
                        {(c) => (
                            <Select
                                {...c}
                                vacia="Elige un tipo"
                                opciones={[
                                    { value: 'oficio', label: 'Oficio' },
                                    { value: 'carta', label: 'Carta' },
                                    { value: 'informe', label: 'Informe' },
                                ]}
                            />
                        )}
                    </FormField>
                    <FormField etiqueta="Palabras clave" ayuda="Enter o coma para agregar.">
                        {(c) => <TagsInput {...c} valor={etiquetas} onCambiar={setEtiquetas} placeholder="Agregar…" />}
                    </FormField>
                    <FormField etiqueta="Texto largo" className="md:col-span-2">
                        {(c) => <Textarea {...c} placeholder="Instrucción de derivación" />}
                    </FormField>
                    <FormField etiqueta="Interruptor">
                        {(c) => <Switch id={c.id} checked={interruptor} onCheckedChange={setInterruptor} />}
                    </FormField>
                    <div className="flex flex-col gap-2">
                        <Checkbox etiqueta="Requiere respuesta" defaultChecked />
                        <Checkbox etiqueta="Deshabilitado" disabled />
                    </div>
                </FormSection>

                <Bloque titulo="Insignias y semáforo">
                    <Fila etiqueta="Semáforo">
                        <SemaforoBadge estado="verde" />
                        <SemaforoBadge estado="amarillo" />
                        <SemaforoBadge estado="rojo" />
                        <SemaforoBadge estado="rojo" texto="Sin responsable" />
                        <SemaforoBadge estado="gris" />
                    </Fila>
                    <Fila etiqueta="Tonos">
                        <Badge>Neutro</Badge>
                        <Badge tono="marca">Marca</Badge>
                        <ActivoBadge activo />
                        <ActivoBadge activo={false} />
                    </Fila>
                </Bloque>

                <section className="overflow-hidden rounded-card border border-border bg-surface shadow-card">
                    <CommandBar
                        acciones={
                            <Button variante="primario" icono={<Add20Regular />}>
                                Registrar
                            </Button>
                        }
                        filtros={<Input iconoInicio={<Search20Regular />} placeholder="Buscar" aria-label="Buscar" className="w-56" />}
                    />
                    <DataTable
                        titulo="Tabla de ejemplo"
                        columnas={columnas}
                        filas={filas}
                        claveFila={(e) => e.id}
                        orden={orden}
                        onOrdenar={(clave) => setOrden((o) => ({ clave, dir: o.clave === clave && o.dir === 'asc' ? 'desc' : 'asc' }))}
                        seleccionada={elegida}
                        onElegirFila={(e) => setElegida(e.id)}
                    />
                    <Pagination
                        links={{ first: null, last: null, prev: null, next: '#' }}
                        meta={{ current_page: 1, last_page: 3, from: 1, to: 4, total: 12, per_page: 4 }}
                    />
                </section>

                <Bloque titulo="Superposiciones y avisos">
                    <Fila etiqueta="Abrir">
                        <Button onClick={() => setDialogo(true)}>Diálogo</Button>
                        <Button onClick={() => setConfirmar(true)}>Confirmación</Button>
                        <Button onClick={() => setDrawer(true)}>Panel lateral</Button>
                        <Button onClick={() => avisar({ tipo: 'ok', mensaje: 'Expediente N°00038 derivado.' })}>Aviso ok</Button>
                        <Button onClick={() => avisar({ tipo: 'error', mensaje: 'No se pudo enviar el correo.' })}>Aviso error</Button>
                        <Button onClick={() => avisar({ tipo: 'info', mensaje: 'La clasificación está en cola.' })}>Aviso info</Button>
                    </Fila>
                </Bloque>

                <Bloque titulo="Carga y vacío">
                    <Fila etiqueta="Cargando">
                        <Spinner className="text-primary-600" />
                        <div className="flex w-64 flex-col gap-2">
                            <Skeleton />
                            <Skeleton className="w-2/3" />
                        </div>
                    </Fila>
                    <EmptyState
                        icono={<Search20Regular />}
                        titulo="Sin resultados"
                        descripcion="Ningún expediente coincide con la búsqueda."
                        accion={<Button>Limpiar filtros</Button>}
                    />
                </Bloque>
            </div>

            <Dialog
                abierto={dialogo}
                onCambiar={setDialogo}
                titulo="Derivar expediente"
                descripcion="Elige el área y la instrucción."
                pie={
                    <>
                        <Button onClick={() => setDialogo(false)}>Cancelar</Button>
                        <Button variante="primario" onClick={() => setDialogo(false)}>
                            Derivar
                        </Button>
                    </>
                }
            >
                <FormField etiqueta="Instrucción">{(c) => <Textarea {...c} />}</FormField>
            </Dialog>
            <ConfirmDialog
                abierto={confirmar}
                onCambiar={setConfirmar}
                titulo="¿Anular el registro N°00040?"
                descripcion="El número no se reutiliza y la anulación queda en la auditoría."
                confirmar="Anular"
                peligro
                onConfirmar={() => setConfirmar(false)}
            />
            {drawer && (
                <Drawer abierto onCambiar={setDrawer} titulo="N°00039 · Requerimiento de información" subtitulo={<SemaforoBadge estado="amarillo" />}>
                    <p className="text-base text-fg">Contenido del expediente con su línea de tiempo.</p>
                </Drawer>
            )}
        </AppShell>
    );
}
