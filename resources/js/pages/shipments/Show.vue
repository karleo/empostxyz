<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create as createInvoice, show as showInvoice } from '@/routes/invoices';
import { index } from '@/routes/shipments';

defineProps<{
    shipment: {
        id: number;
        tracking_number: string;
        uhawb: string | null;
        invoice_id: number | null;
        payload: Record<string, unknown>;
        sync_status: string;
        sync_message: string | null;
        sync_errors: unknown[] | null;
        correlation_id: string | null;
        source: string;
        invoice?: {
            id: number;
            invoice_number: string | null;
            sync_status: string;
        } | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Shipments', href: index() },
            { title: 'Details', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`Shipment ${shipment.tracking_number}`" />

    <div class="mx-auto flex max-w-4xl flex-col gap-6 p-4">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                :title="shipment.tracking_number"
                description="Shipments stay local until linked to an invoice and submitted."
            />
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="!shipment.invoice_id"
                    as-child
                >
                    <Link
                        :href="
                            createInvoice.url({
                                query: { shipments: String(shipment.id) },
                            })
                        "
                    >
                        Create invoice
                    </Link>
                </Button>
                <Button
                    v-else-if="shipment.invoice"
                    variant="outline"
                    as-child
                >
                    <Link :href="showInvoice(shipment.invoice.id)">
                        Open invoice
                    </Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="index()">Back to list</Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-4 rounded-xl border p-4 sm:grid-cols-2">
            <div>
                <p class="text-sm text-muted-foreground">Status</p>
                <Badge class="mt-1">{{ shipment.sync_status }}</Badge>
            </div>
            <div>
                <p class="text-sm text-muted-foreground">Invoice</p>
                <p class="mt-1 font-medium">
                    <template v-if="shipment.invoice">
                        {{
                            shipment.invoice.invoice_number ||
                            `#${shipment.invoice.id}`
                        }}
                    </template>
                    <template v-else>Not linked</template>
                </p>
            </div>
            <div class="sm:col-span-2">
                <p class="text-sm text-muted-foreground">Message</p>
                <p class="mt-1">{{ shipment.sync_message || '—' }}</p>
            </div>
        </div>

        <div class="rounded-xl border p-4">
            <h2 class="mb-2 font-medium">Payload</h2>
            <pre class="overflow-x-auto text-sm">{{
                JSON.stringify(shipment.payload, null, 2)
            }}</pre>
        </div>
    </div>
</template>
