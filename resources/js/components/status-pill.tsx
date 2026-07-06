import { cn } from '@/lib/utils';

type Tone = 'up' | 'down' | 'warn' | 'neutral';

const DOT_CLASSES: Record<Tone, string> = {
    up: 'bg-emerald-500',
    down: 'bg-red-500',
    warn: 'bg-amber-500',
    neutral: 'bg-muted-foreground/50',
};

const PILL_CLASSES: Record<Tone, string> = {
    up: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    down: 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-400',
    warn: 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400',
    neutral: 'border-border bg-muted text-muted-foreground',
};

const STATUS_TONE: Record<string, Tone> = {
    up: 'up',
    operational: 'up',
    resolved: 'up',
    down: 'down',
    open: 'down',
    degraded: 'warn',
    investigating: 'warn',
    monitoring: 'warn',
    paused: 'neutral',
    unknown: 'neutral',
};

export function statusTone(status: string): Tone {
    return STATUS_TONE[status.toLowerCase()] ?? 'neutral';
}

export function StatusPill({ status, label, className }: { status: string; label?: string; className?: string }) {
    const tone = statusTone(status);

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium capitalize whitespace-nowrap',
                PILL_CLASSES[tone],
                className,
            )}
        >
            <span className={cn('size-1.5 rounded-full', DOT_CLASSES[tone])} />
            {label ?? status}
        </span>
    );
}
