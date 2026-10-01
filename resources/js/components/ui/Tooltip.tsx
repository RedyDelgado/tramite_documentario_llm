import { Tooltip as T } from 'radix-ui';
import type { ReactNode } from 'react';

export function Tooltip({ texto, children }: { texto: string; children: ReactNode }) {
    return (
        <T.Provider delayDuration={400}>
            <T.Root>
                <T.Trigger asChild>{children}</T.Trigger>
                <T.Portal>
                    <T.Content
                        sideOffset={4}
                        className="z-50 rounded-control bg-fg px-2 py-1 text-sm text-on-primary shadow-card"
                    >
                        {texto}
                    </T.Content>
                </T.Portal>
            </T.Root>
        </T.Provider>
    );
}
