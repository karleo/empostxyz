<script setup lang="ts">
import { Form, Head, Link, useForm } from '@inertiajs/vue3';
import InvoiceController from '@/actions/App/Http/Controllers/Invoices/InvoiceController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index, show } from '@/routes/invoices';
import { show as showShipment } from '@/routes/shipments';

type ShipmentRow = {
    id: number;
    tracking_number: string;
    uhawb: string | null;
    sync_status: string;
    sync_message: string | null;
    source: string;
};

type Linkable = {
    id: number;
    tracking_number: string;
    uhawb: string | null;
    sync_status: string;
    source: string;
};

const props = defineProps<{
    invoice: {
        id: number;
        invoice_number: string | null;
        payload: Record<string, unknown>;
        sync_status: string;
        sync_message: string | null;
        sync_errors: unknown[] | null;
        correlation_id: string | null;
        last_synced_at: string | null;
        shipments: ShipmentRow[];
    };
    linkableShipments: Linkable[];
    canSubmit: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Invoices', href: index() },
            { title: 'Details', href: '#' },
        ],
    },
});

const linkForm = useForm({
    shipment_ids: [] as number[],
});

function toggleLink(id: number, checked: boolean) {
    if (checked) {
        linkForm.shipment_ids = [...linkForm.shipment_ids, id];
    } else {
        linkForm.shipment_ids = linkForm.shipment_ids.filter((x) => x !== id);
    }
}

function linkShipments() {
    linkForm.post(InvoiceController.link.url(props.invoice.id));
}
</script>

<template>
    <Head :title="`Invoice ${invoice.invoice_number || invoice.id}`" />

    <div class="mx-auto flex max-w-4xl flex-col gap-6 p-4">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                :title="invoice.invoice_number || `Invoice #${invoice.id}`"
                description="Multiple shipments linked to one invoice. Submit sends all to EPGL."
            />
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <Link :href="index()">Back to list</Link>
                </Button>
                <Form
                    v-if="canSubmit"
                    v-bind="InvoiceController.submit.form(invoice.id)"
                    v-slot="{ processing }"
                >
                    <Button type="submit" :disabled="processing">
                        Submit to EPGL
                    </Button>
                </Form>
            </div>
        </div>

        <div class="grid gap-4 rounded-xl border p-4 sm:grid-cols-2">
            <div>
                <p class="text-sm text-muted-foreground">Status</p>
                <Badge class="mt-1">{{ invoice.sync_status }}</Badge>
            </div>
            <div>
                <p class="text-sm text-muted-foreground">Shipments</p>
                <p class="mt-1 font-medium">{{ invoice.shipments.length }}</p>
            </div>
            <div class="sm:col-span-2">
                <p class="text-sm text-muted-foreground">Message</p>
                <p class="mt-1">{{ invoice.sync_message || '—' }}</p>
            </div>
        </div>

        <section class="rounded-xl border p-4">
            <h2 class="mb-3 font-medium">Linked shipments</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th class="px-3 py-2">Tracking</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Message</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="shipment in invoice.shipments"
                            :key="shipment.id"
                            class="border-b last:border-0"
                        >
                            <td class="px-3 py-2 font-medium">
                                {{ shipment.tracking_number }}
                            </td>
                            <td class="px-3 py-2">
                                <Badge>{{ shipment.sync_status }}</Badge>
                            </td>
                            <td class="max-w-xs truncate px-3 py-2 text-muted-foreground">
                                {{ shipment.sync_message || '—' }}
                            </td>
                            <td class="px-3 py-2 text-right">
                                <Button variant="ghost" size="sm" as-child>
                                    <Link :href="showShipment(shipment.id)">
                                        View
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section
            v-if="linkableShipments.length && (invoice.sync_status === 'draft' || invoice.sync_status === 'failed' || invoice.sync_status === 'partial')"
            class="space-y-3 rounded-xl border p-4"
        >
            <h2 class="font-medium">Link more shipments</h2>
            <div class="max-h-56 space-y-2 overflow-y-auto">
                <label
                    v-for="shipment in linkableShipments"
                    :key="shipment.id"
                    class="flex items-center gap-3 rounded-lg border p-2 text-sm"
                >
                    <input
                        type="checkbox"
                        class="size-4 accent-primary"
                        :checked="linkForm.shipment_ids.includes(shipment.id)"
                        @change="
                            toggleLink(
                                shipment.id,
                                ($event.target as HTMLInputElement).checked,
                            )
                        "
                    />
                    {{ shipment.tracking_number }}
                </label>
            </div>
            <Button
                type="button"
                variant="outline"
                :disabled="!linkForm.shipment_ids.length || linkForm.processing"
                @click="linkShipments"
            >
                Link selected
            </Button>
        </section>

        <div
            v-if="invoice.sync_errors?.length"
            class="rounded-xl border border-destructive/30 p-4"
        >
            <h2 class="mb-2 font-medium">Errors</h2>
            <pre class="overflow-x-auto text-sm">{{
                JSON.stringify(invoice.sync_errors, null, 2)
            }}</pre>
        </div>

        <div class="rounded-xl border p-4">
            <h2 class="mb-2 font-medium">Invoice payload</h2>
            <pre class="overflow-x-auto text-sm">{{
                JSON.stringify(invoice.payload, null, 2)
            }}</pre>
        </div>
    </div>
</template>
