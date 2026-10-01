import { cn } from '@/lib/cn';

export function Spinner({ className, etiqueta = 'Cargando' }: { className?: string; etiqueta?: string }) {
    return (
        <svg
            viewBox="0 0 16 16"
            role="status"
            aria-label={etiqueta}
            className={cn('size-4 animate-spin', className)}
        >
            <circle cx="8" cy="8" r="6.5" fill="none" stroke="currentColor" strokeOpacity="0.25" strokeWidth="2" />
            <path d="M14.5 8A6.5 6.5 0 0 0 8 1.5" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
        </svg>
    );
}
