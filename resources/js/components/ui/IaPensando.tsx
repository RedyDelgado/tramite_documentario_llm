import { cn } from '@/lib/cn';
import { IcoIa } from './iconos';

/** Solo mientras trabaja la IA, para que el cerebro signifique eso y nada más; el resto de esperas usa Spinner. */
export function IaPensando({ children, className }: { children: string; className?: string }) {
    return (
        <span role="status" className={cn('inline-flex items-center gap-2.5', className)}>
            <span className="relative inline-flex shrink-0 text-primary-600">
                <span aria-hidden className="ia-halo absolute inset-[-5px] rounded-full bg-primary-100" />
                <IcoIa size={22} className="ia-circuito relative" />
            </span>
            <span>
                {children}
                <span aria-hidden className="ia-puntos">
                    <span>.</span>
                    <span>.</span>
                    <span>.</span>
                </span>
            </span>
        </span>
    );
}
