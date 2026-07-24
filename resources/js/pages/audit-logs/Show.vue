<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/audit-logs';
import { show as showInvoice } from '@/routes/invoices';
import { show as showShipment } from '@/routes/shipments';

defineProps<{
    log: {
        id: number;
        action: string;
        endpoint: string;
        method: string;
        tracking_number: string | null;
        request_payload: Record<string, unknown> | null;
        response_body: Record<string, unknown> | null;
        http_status: number | null;
        successful: boolean;
        correlation_id: string | null;
        message: string | null;
        created_at: string;
        user?: { id: number; name: string; email: string } | null;
        invoice?: {
            id: number;
            invoice_number: string | null;
            sync_status: string;
        } | null;
        shipment?: {
            id: number;
            tracking_number: string;
            sync_status: string;
        } | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Audit logs', href: index() },
            { title: 'Details', href: '#' },
        ],
    },
});

function actionLabel(action: string) {
    if (action === 'create_shipment') {
        return 'Create shipment';
    }
    if (action === 'issue_invoice') {
        return 'Issue invoice';
    }
    return action;
}
</script>

<template>
    <Head :title="`Audit log #${log.id}`" />

    <div class="flex min-h-full flex-col">
        <div class="border-b bg-muted/20 px-4 py-6 sm:px-6 lg:px-8">
            <div
                class="mx-auto flex max-w-5xl flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
            >
                <Heading
                    :title="`Audit log #${log.id}`"
                    :description="actionLabel(log.action)"
                />
                <Button variant="outline" as-child>
                    <Link :href="index()">Back to history</Link>
                </Button>
            </div>
        </div>

        <div class="mx-auto flex w-full max-w-5xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
            <section class="grid gap-4 rounded-xl border bg-card p-5 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Submitted by
                    </p>
                    <p v-if="log.user" class="mt-1 font-medium">
                        {{ log.user.name }}
                    </p>
                    <p v-if="log.user" class="text-sm text-muted-foreground">
                        {{ log.user.email }}
                    </p>
                    <p v-else class="mt-1 text-muted-foreground">System</p>
                </div>
                <div>
                    <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        When
                    </p>
                    <p class="mt-1 font-medium">
                        {{ new Date(log.created_at).toLocaleString() }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Result
                    </p>
                    <Badge
                        class="mt-2"
                        :variant="log.successful ? 'default' : 'destructive'"
                    >
                        {{ log.successful ? 'Success' : 'Failed' }}
                        <template v-if="log.http_status">
                            · HTTP {{ log.http_status }}
                        </template>
                    </Badge>
                </div>
                <div>
                    <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Tracking
                    </p>
                    <p class="mt-1 font-medium tracking-tight">
                        {{ log.tracking_number || '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Correlation ID
                    </p>
                    <p class="mt-1 break-all font-mono text-sm">
                        {{ log.correlation_id || '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Endpoint
                    </p>
                    <p class="mt-1 break-all font-mono text-xs">
                        {{ log.method }} {{ log.endpoint }}
                    </p>
                </div>
                <div v-if="log.shipment" class="sm:col-span-2 lg:col-span-1">
                    <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Shipment
                    </p>
                    <Button variant="link" class="h-auto px-0" as-child>
                        <Link :href="showShipment(log.shipment.id)">
                            {{ log.shipment.tracking_number }}
                        </Link>
                    </Button>
                </div>
                <div v-if="log.invoice">
                    <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Invoice
                    </p>
                    <Button variant="link" class="h-auto px-0" as-child>
                        <Link :href="showInvoice(log.invoice.id)">
                            {{
                                log.invoice.invoice_number ||
                                `#${log.invoice.id}`
                            }}
                        </Link>
                    </Button>
                </div>
                <div class="sm:col-span-2 lg:col-span-3">
                    <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Message
                    </p>
                    <p class="mt-1">{{ log.message || '—' }}</p>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border bg-card">
                <div class="border-b px-5 py-3">
                    <h2 class="text-sm font-semibold">Request payload</h2>
                </div>
                <pre
                    class="max-h-[28rem] overflow-auto bg-muted/30 p-5 text-xs leading-relaxed"
                    >{{ JSON.stringify(log.request_payload, null, 2) }}</pre
                >
            </section>

            <section class="overflow-hidden rounded-xl border bg-card">
                <div class="border-b px-5 py-3">
                    <h2 class="text-sm font-semibold">Response</h2>
                </div>
                <pre
                    class="max-h-[28rem] overflow-auto bg-muted/30 p-5 text-xs leading-relaxed"
                    >{{ JSON.stringify(log.response_body, null, 2) }}</pre
                >
            </section>
        </div>
    </div>
</template>
