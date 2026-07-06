import { StatusPill } from '@/components/status-pill';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

interface Monitor {
    id: number;
    name: string;
    url: string;
    status: 'up' | 'down' | 'paused' | 'unknown';
    interval_seconds: number;
    failure_threshold: number;
    last_checked_at: string | null;
}

interface DashboardIncident {
    id: number;
    title: string;
    status: string;
    is_auto: boolean;
    started_at: string | null;
    resolved_at: string | null;
}

interface DashboardTenant {
    name: string;
    slug: string;
    plan: string;
    monitor_limit: number;
}

interface DashboardProps {
    monitors: Monitor[];
    incidents: DashboardIncident[];
    tenant: DashboardTenant;
}

const DEFAULT_FORM = {
    name: '',
    url: '',
    interval_seconds: 60,
    failure_threshold: 2,
};

function formatTimestamp(value: string | null): string {
    if (!value) {
        return 'Never';
    }

    return new Date(value).toLocaleString();
}

export default function Dashboard({ monitors, incidents, tenant }: DashboardProps) {
    const [form, setForm] = useState(DEFAULT_FORM);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [limitError, setLimitError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    const atLimit = monitors.length >= tenant.monitor_limit;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        setErrors({});
        setLimitError(null);
        setProcessing(true);

        router.post(
            '/monitors',
            { ...form },
            {
                preserveScroll: true,
                onSuccess: () => setForm(DEFAULT_FORM),
                onError: (formErrors) => {
                    if (Object.keys(formErrors).length > 0) {
                        setErrors(formErrors as Record<string, string>);
                    } else {
                        setLimitError('Monitor limit reached. Upgrade your plan to add more.');
                    }
                },
                onFinish: () => setProcessing(false),
            },
        );
    };

    const destroy = (id: number) => {
        router.delete(`/monitors/${id}`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-xl font-semibold tracking-tight">{tenant.name}</h1>
                        <p className="text-muted-foreground text-sm capitalize">{tenant.plan} plan</p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <Button variant="outline" asChild>
                            <a href={`/status/${tenant.slug}`} target="_blank" rel="noopener noreferrer">
                                View status page
                            </a>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href="/billing">Billing</a>
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle className="text-sm font-medium">Monitors used</CardTitle>
                        <span className="text-muted-foreground text-sm">
                            {monitors.length} / {tenant.monitor_limit}
                        </span>
                    </CardHeader>
                    <CardContent>
                        <div className="bg-muted h-2 w-full overflow-hidden rounded-full">
                            <div
                                className={`h-full rounded-full ${atLimit ? 'bg-red-500' : 'bg-primary'}`}
                                style={{
                                    width: `${Math.min(100, (monitors.length / Math.max(tenant.monitor_limit, 1)) * 100)}%`,
                                }}
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Add monitor</CardTitle>
                        <CardDescription>Track a URL and get alerted when it goes down.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    value={form.name}
                                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                                    required
                                    placeholder="API"
                                />
                                {errors.name && <p className="text-sm text-red-600 dark:text-red-400">{errors.name}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="url">URL</Label>
                                <Input
                                    id="url"
                                    type="url"
                                    value={form.url}
                                    onChange={(e) => setForm({ ...form, url: e.target.value })}
                                    required
                                    placeholder="https://example.com"
                                />
                                {errors.url && <p className="text-sm text-red-600 dark:text-red-400">{errors.url}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="interval_seconds">Check interval (seconds)</Label>
                                <Input
                                    id="interval_seconds"
                                    type="number"
                                    min={5}
                                    value={form.interval_seconds}
                                    onChange={(e) => setForm({ ...form, interval_seconds: Number(e.target.value) })}
                                    required
                                />
                                {errors.interval_seconds && <p className="text-sm text-red-600 dark:text-red-400">{errors.interval_seconds}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="failure_threshold">Failure threshold</Label>
                                <Input
                                    id="failure_threshold"
                                    type="number"
                                    min={1}
                                    value={form.failure_threshold}
                                    onChange={(e) => setForm({ ...form, failure_threshold: Number(e.target.value) })}
                                    required
                                />
                                {errors.failure_threshold && <p className="text-sm text-red-600 dark:text-red-400">{errors.failure_threshold}</p>}
                            </div>

                            {limitError && (
                                <p className="rounded-md border border-red-500/30 bg-red-500/10 p-2 text-sm text-red-600 sm:col-span-2 dark:text-red-400">
                                    {limitError}
                                </p>
                            )}

                            <div className="sm:col-span-2">
                                <Button type="submit" disabled={processing}>
                                    Add monitor
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Monitors</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        {monitors.length === 0 ? (
                            <p className="text-muted-foreground p-6 text-sm">No monitors yet. Add one above to start tracking uptime.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b text-left">
                                            <th className="px-6 py-3 font-medium">Name</th>
                                            <th className="px-6 py-3 font-medium">URL</th>
                                            <th className="px-6 py-3 font-medium">Status</th>
                                            <th className="px-6 py-3 font-medium">Interval</th>
                                            <th className="px-6 py-3 font-medium">Last checked</th>
                                            <th className="px-6 py-3 font-medium" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {monitors.map((monitor) => (
                                            <tr key={monitor.id} className="border-b last:border-b-0">
                                                <td className="px-6 py-3 font-medium">{monitor.name}</td>
                                                <td className="max-w-[220px] truncate px-6 py-3">
                                                    <a
                                                        href={monitor.url}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="text-muted-foreground hover:text-foreground underline underline-offset-4"
                                                    >
                                                        {monitor.url}
                                                    </a>
                                                </td>
                                                <td className="px-6 py-3">
                                                    <StatusPill status={monitor.status} />
                                                </td>
                                                <td className="text-muted-foreground px-6 py-3">{monitor.interval_seconds}s</td>
                                                <td className="text-muted-foreground px-6 py-3">{formatTimestamp(monitor.last_checked_at)}</td>
                                                <td className="px-6 py-3 text-right">
                                                    <Button variant="ghost" size="sm" onClick={() => destroy(monitor.id)}>
                                                        Delete
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Incidents</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        {incidents.length === 0 ? (
                            <p className="text-muted-foreground p-6 text-sm">No incidents recorded.</p>
                        ) : (
                            <ul className="divide-y">
                                {incidents.map((incident) => (
                                    <li key={incident.id} className="flex flex-col gap-2 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div className="flex flex-col gap-1">
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium">{incident.title}</span>
                                                <span className="text-muted-foreground rounded-full border px-2 py-0.5 text-xs">
                                                    {incident.is_auto ? 'Automatic' : 'Manual'}
                                                </span>
                                            </div>
                                            <p className="text-muted-foreground text-xs">
                                                Started {formatTimestamp(incident.started_at)}
                                                {incident.resolved_at && ` · Resolved ${formatTimestamp(incident.resolved_at)}`}
                                            </p>
                                        </div>
                                        <StatusPill status={incident.status} />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
