import { Switch as S } from 'radix-ui';
import type { ComponentProps } from 'react';
import { cn } from '@/lib/cn';

type Props = Omit<ComponentProps<typeof S.Root>, 'children'> & { etiqueta?: string };

export function Switch({ etiqueta, className, id, ...props }: Props) {
    const control = (
        <S.Root
            id={id}
            className={cn(
                'relative inline-flex h-5 w-10 shrink-0 cursor-pointer items-center rounded-full border border-fg-muted bg-surface transition-colors data-[state=checked]:border-primary-600 data-[state=checked]:bg-primary-600 disabled:cursor-not-allowed disabled:border-border disabled:bg-surface-subtle disabled:data-[state=checked]:border-fg-disabled disabled:data-[state=checked]:bg-fg-disabled',
                !etiqueta && className,
            )}
            {...props}
        >
            <S.Thumb className="block size-3 translate-x-[3px] rounded-full bg-fg-muted transition-transform data-[state=checked]:translate-x-[23px] data-[state=checked]:bg-on-primary" />
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
