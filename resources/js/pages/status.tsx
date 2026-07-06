import { StatusPill } from '@/components/status-pill';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Head } from '@inertiajs/react';
import { FormEvent, useEffect, useRef, useState } from 'react';

interface StatusMonitor {
    id: number;
    name: string;
    status: string;
    last_checked_at: string | null;
}

interface StatusIncidentUpdate {
    message: string;
    status: string;
    created_at: string | null;
}

interface StatusIncident {
    id: number;
    title: string;
    status: string;
    started_at: string | null;
    resolved_at: string | null;
    updates: StatusIncidentUpdate[];
}

interface StatusSnapshot {
    tenant_name: string;
    slug: string;
    overall: 'operational' | 'degraded' | 'down';
    monitors: StatusMonitor[];
    incidents: StatusIncident[];
}

const BANNER_COPY: Record<StatusSnapshot['overall'], { title: string; classes: string }> = {
    operational: {
        title: 'All systems operational',
        classes: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
    },
    degraded: {
        title: 'Degraded performance',
        classes: 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400',
    },
    down: {
        title: 'Service disruption',
        classes: 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-400',
    },
};

function formatTimestamp(value: string | null): string {
    if (!value) {
        return 'Never';
    }

    return new Date(value).toLocaleString();
}

function readCookie(name: string): string | null {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));

    return match ? decodeURIComponent(match[1]) : null;
}

export default function Status({ status }: { status: StatusSnapshot }) {
    const [snapshot, setSnapshot] = useState(status);
    const [email, setEmail] = useState('');
    const [subscribeState, setSubscribeState] = useState<'idle' | 'submitting' | 'done' | 'error'>('idle');

    const slug = useRef(status.slug).current;

    useEffect(() => {
        const interval = setInterval(async () => {
            try {
                const response = await fetch(`/status/${slug}/data`, {
                    headers: { Accept: 'application/json' },
                });

                if (response.ok) {
                    setSnapshot(await response.json());
                }
            } catch {
                // Ignore transient network errors; we'll try again on the next tick.
            }
        }, 5000);

        return () => clearInterval(interval);
    }, [slug]);

    const subscribe = async (e: FormEvent) => {
        e.preventDefault();
        setSubscribeState('submitting');

        try {
            const response = await fetch(`/status/${slug}/subscribe`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': readCookie('XSRF-TOKEN') ?? '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ email }),
            });

            setSubscribeState(response.status === 201 ? 'done' : 'error');
        } catch {
            setSubscribeState('error');
        }
    };

    const banner = BANNER_COPY[snapshot.overall];

    return (
        <>
            <Head title={`${snapshot.tenant_name} status`} />

            <div className="bg-background min-h-screen px-4 py-12">
                <div className="mx-auto flex max-w-2xl flex-col gap-8">
                    <div className="text-center">
                        <h1 className="text-2xl font-semibold tracking-tight">{snapshot.tenant_name}</h1>
                        <p className="text-muted-foreground text-sm">Status page</p>
                    </div>

                    <div className={`rounded-lg border p-6 text-center text-lg font-medium ${banner.classes}`}>{banner.title}</div>

                    <Card>
                        <CardHeader>
                            <CardTitle>Monitors</CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            {snapshot.monitors.length === 0 ? (
                                <p className="text-muted-foreground p-6 text-sm">No monitors configured yet.</p>
                            ) : (
                                <ul className="divide-y">
                                    {snapshot.monitors.map((monitor) => (
                                        <li key={monitor.id} className="flex items-center justify-between px-6 py-4">
                                            <div>
                                                <p className="font-medium">{monitor.name}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    Last checked {formatTimestamp(monitor.last_checked_at)}
                                                </p>
                                            </div>
                                            <StatusPill status={monitor.status} />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Incidents</CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            {snapshot.incidents.length === 0 ? (
                                <p className="text-muted-foreground p-6 text-sm">No incidents in the past 7 days.</p>
                            ) : (
                                <ul className="divide-y">
                                    {snapshot.incidents.map((incident) => (
                                        <li key={incident.id} className="flex flex-col gap-3 px-6 py-4">
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="font-medium">{incident.title}</span>
                                                <StatusPill status={incident.status} />
                                            </div>
                                            <p className="text-muted-foreground text-xs">
                                                Started {formatTimestamp(incident.started_at)}
                                                {incident.resolved_at && ` · Resolved ${formatTimestamp(incident.resolved_at)}`}
                                            </p>

                                            {incident.updates.length > 0 && (
                                                <ol className="border-muted ml-2 flex flex-col gap-2 border-l pl-4">
                                                    {incident.updates.map((update, index) => (
                                                        <li key={index} className="text-sm">
                                                            <span className="text-muted-foreground text-xs">
                                                                {formatTimestamp(update.created_at)} ·{' '}
                                                            </span>
                                                            <span className="font-medium capitalize">{update.status}</span>
                                                            {update.message && <span className="text-muted-foreground"> — {update.message}</span>}
                                                        </li>
                                                    ))}
                                                </ol>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Get notified</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {subscribeState === 'done' ? (
                                <p className="text-sm text-emerald-600 dark:text-emerald-400">Thanks — you're subscribed to status updates.</p>
                            ) : (
                                <form onSubmit={subscribe} className="flex flex-col gap-3 sm:flex-row">
                                    <Input
                                        type="email"
                                        required
                                        placeholder="you@example.com"
                                        value={email}
                                        onChange={(e) => setEmail(e.target.value)}
                                        className="sm:flex-1"
                                    />
                                    <Button type="submit" disabled={subscribeState === 'submitting'}>
                                        Subscribe
                                    </Button>
                                </form>
                            )}
                            {subscribeState === 'error' && (
                                <p className="mt-2 text-sm text-red-600 dark:text-red-400">Something went wrong. Please try again.</p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}
