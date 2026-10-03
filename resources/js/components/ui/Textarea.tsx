import type { TextareaHTMLAttributes } from 'react';
import { cn } from '@/lib/cn';
import { campoClases } from './campo';

export function Textarea({ className, rows = 3, ...props }: TextareaHTMLAttributes<HTMLTextAreaElement>) {
    return <textarea rows={rows} className={cn(campoClases, 'min-h-20 resize-y py-2', className)} {...props} />;
}
