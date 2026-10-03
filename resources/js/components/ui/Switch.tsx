import { Switch as S } from 'radix-ui';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/cn';

type Props = Omit<ComponentProps<typeof S.Root>, 'children'> & { etiqueta?: string };

export function Switch({ etiqueta, className, id, ...props }: Props) {
    const control = (
        <S.Root
            id={id}
            className={cn(
                'relative inline-flex h-6 w-10 shrink-0 cursor-pointer items-center rounded-full bg-border-campo/60 transition-colors data-[state=checked]:bg-primary-600 disabled:cursor-not-allowed disabled:opacity-40',
                !etiqueta && className,
            )}
            {...props}
        >
            <S.Thumb className="block size-5 translate-x-0.5 rounded-full bg-surface shadow-card transition-transform data-[state=checked]:translate-x-[18px]" />
        </S.Root>
    );

    if (!etiqueta) return control;

    return (
        <label className={cn('inline-flex cursor-pointer items-center gap-2 text-base text-fg', className)}>
            {control}
            {etiqueta}
        </label>
    );
}
