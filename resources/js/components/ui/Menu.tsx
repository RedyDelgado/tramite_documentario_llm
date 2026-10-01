import { DropdownMenu as M } from 'radix-ui';
import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';

export type ItemMenu = { etiqueta: string; icono?: ReactNode; onSelect: () => void; peligro?: boolean };

type Props = {
    disparador: ReactNode;
    encabezado?: ReactNode;
    items: ItemMenu[];
};

export function Menu({ disparador, encabezado, items }: Props) {
    return (
        <M.Root>
            <M.Trigger asChild>{disparador}</M.Trigger>
            <M.Portal>
                <M.Content
                    align="end"
                    sideOffset={4}
                    className="z-50 min-w-48 rounded-card border border-border bg-surface py-1 shadow-card"
                >
                    {encabezado && (
                        <>
                            <div className="px-3 py-2">{encabezado}</div>
                            <M.Separator className="my-1 h-px bg-border" />
                        </>
                    )}
                    {items.map((item) => (
                        <M.Item
                            key={item.etiqueta}
                            onSelect={item.onSelect}
                            className={cn(
                                'flex h-8 cursor-pointer items-center gap-2 px-3 text-base outline-none data-highlighted:bg-primary-50',
                                item.peligro ? 'text-danger' : 'text-fg',
                            )}
                        >
                            {item.icono}
                            {item.etiqueta}
                        </M.Item>
                    ))}
                </M.Content>
            </M.Portal>
        </M.Root>
    );
}
