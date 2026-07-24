<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FileText, Plus } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index as invoicesIndex, create, show } from '@/routes/invoices';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Invoices',
                href: invoicesIndex(),
            },
        ],
    },
});

type InvoiceRow = {
    id: number;
    invoice_number: string | null;
    sync_status: 'draft' | 'pending' | 'success' | 'partial' | 'failed';
    sync_message: string | null;
    shipments_count: number;
    last_synced_at: string | null;
    created_at: string;
};

defineProps<{
    invoices: {
        data: InvoiceRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { status: string };
}>();

function statusVariant(status: string) {
    if (status === 'success') {
        return 'default' as const;
    }
    if (status === 'failed' || status === 'partial') {
        return 'destructive' as const;
    }
    if (status === 'draft') {
        return 'outline' as const;
    }
    return 'secondary' as const;
}

function filterBy(status: string) {
    router.get(
        invoicesIndex.url({ query: status ? { status } : {} }),
        {},
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head title="Invoices" />

    <div class="flex flex-col gap-6 p-4">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                title="Invoices"
                description="One invoice can cover many shipments. Submit sends all linked shipments to EPGL."
            />
            <Button as-child>
                <Link :href="create()">
                    <Plus class="size-4" />
                    New invoice
                </Link>
            </Button>
        </div>

        <div class="flex flex-wrap gap-2">
            <Button
                size="sm"
                :variant="!filters.status ? 'default' : 'outline'"
                @click="filterBy('')"
            >
                All
            </Button>
            <Button
                size="sm"
                :variant="filters.status === 'draft' ? 'default' : 'outline'"
                @click="filterBy('draft')"
            >
                Draft
            </Button>
            <Button
                size="sm"
                :variant="filters.status === 'success' ? 'default' : 'outline'"
                @click="filterBy('success')"
            >
                Success
            </Button>
            <Button
                size="sm"
                :variant="filters.status === 'failed' ? 'default' : 'outline'"
                @click="filterBy('failed')"
            >
                Failed
            </Button>
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="border-b bg-muted/40">
                    <tr>
                        <th class="px-4 py-3 font-medium">Invoice #</th>
                        <th class="px-4 py-3 font-medium">Shipments</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Message</th>
                        <th class="px-4 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="invoices.data.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            <FileText class="mx-auto mb-2 size-8 opacity-40" />
                            No invoices yet.
                        </td>
                    </tr>
                    <tr
                        v-for="invoice in invoices.data"
                        :key="invoice.id"
                        class="border-b last:border-0"
                    >
                        <td class="px-4 py-3 font-medium">
                            {{ invoice.invoice_number || `#${invoice.id}` }}
                        </td>
                        <td class="px-4 py-3">
                            {{ invoice.shipments_count }}
                        </td>
                        <td class="px-4 py-3">
                            <Badge :variant="statusVariant(invoice.sync_status)">
                                {{ invoice.sync_status }}
                            </Badge>
                        </td>
                        <td
                            class="max-w-xs truncate px-4 py-3 text-muted-foreground"
                        >
                            {{ invoice.sync_message || '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Button variant="ghost" size="sm" as-child>
                                <Link :href="show(invoice.id)">View</Link>
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
