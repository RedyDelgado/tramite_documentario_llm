import { ErrorCircle16Regular } from '@fluentui/react-icons';
import { useId, type ReactNode } from 'react';
import { cn } from '@/lib/cn';

export type PropsControl = {
    id: string;
    'aria-invalid'?: boolean;
    'aria-describedby'?: string;
    required?: boolean;
};

type Props = {
    etiqueta: string;
    error?: string;
    ayuda?: string;
    requerido?: boolean;
    className?: string;
    // Render prop: el campo cablea id, aria-invalid y aria-describedby una sola vez para todos los controles.
    children: (control: PropsControl) => ReactNode;
};

export function FormField({ etiqueta, error, ayuda, requerido = false, className, children }: Props) {
    const id = useId();
    const idAyuda = `${id}-ayuda`;
    const idError = `${id}-error`;
    const descritoPor = [ayuda && idAyuda, error && idError].filter(Boolean).join(' ') || undefined;

    return (
        <div className={cn('flex flex-col gap-1', className)}>
            <label htmlFor={id} className="text-base font-semibold text-fg">
                {etiqueta}
                {requerido && (
                    <span className="text-danger" aria-hidden>
                        {' '}*
                    </span>
                )}
            </label>
            {children({ id, 'aria-invalid': error ? true : undefined, 'aria-describedby': descritoPor, required: requerido || undefined })}
            {ayuda && !error && (
                <p id={idAyuda} className="text-sm text-fg-muted">
                    {ayuda}
                </p>
            )}
            {error && (
                <p id={idError} className="flex items-center gap-1 text-sm text-danger">
                    <ErrorCircle16Regular className="shrink-0" />
                    {error}
                </p>
            )}
        </div>
    );
}
