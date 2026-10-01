import { cn } from '@/lib/cn';

export function Skeleton({ className }: { className?: string }) {
    return <div aria-hidden className={cn('h-4 animate-pulse rounded-control bg-border', className)} />;
}
