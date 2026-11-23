import type { TextareaHTMLAttributes } from 'react';
import { cn } from '@/lib/cn';
import { campoClases } from './campo';

// `compacto`: una línea que crece hasta un tope, para escribir mensajes (chat) sin un bloque grande vacío.
type Props = TextareaHTMLAttributes<HTMLTextAreaElement> & { compacto?: boolean };

export function Textarea({ className, rows = 3, compacto = false, ...props }: Props) {
    return (
        <textarea
            rows={compacto ? 1 : rows}
            className={cn(campoClases, compacto ? 'field-sizing-content max-h-40 min-h-10 resize-none py-2' : 'min-h-20 resize-y py-2', className)}
            {...props}
        />
    );
}
