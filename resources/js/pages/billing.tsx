import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Billing',
        href: '/billing',
    },
];

interface BillingInfo {
    plan: string;
    monitor_limit: number;
    configured: boolean;
}

export default function Billing({ billing }: { billing: BillingInfo }) {
    const [processing, setProcessing] = useState(false);

    const checkout = () => {
        setProcessing(true);
        router.post('/billing/checkout', {}, { onFinish: () => setProcessing(false) });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Billing" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Current plan</CardTitle>
                        <CardDescription>Your subscription and monitor allowance.</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div className="flex items-center justify-between">
                            <span className="text-muted-foreground text-sm">Plan</span>
                            <span className="font-medium capitalize">{billing.plan}</span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="text-muted-foreground text-sm">Monitor limit</span>
                            <span className="font-medium">{billing.monitor_limit}</span>
                        </div>

                        {billing.configured ? (
                            <Button onClick={checkout} disabled={processing} className="w-fit">
                                Upgrade
                            </Button>
                        ) : (
                            <Alert>
                                <AlertTitle>Stripe not configured</AlertTitle>
                                <AlertDescription>Add STRIPE_* keys to .env to enable checkout.</AlertDescription>
                            </Alert>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
