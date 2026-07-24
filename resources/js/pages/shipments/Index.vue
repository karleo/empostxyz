<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FileText, Package, Plus, Search, Upload, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create as createInvoice } from '@/routes/invoices';
import { index as shipmentsIndex, create, upload, show } from '@/routes/shipments';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Shipments',
                href: shipmentsIndex(),
            },
        ],
    },
});

type ShipmentRow = {
    id: number;
    tracking_number: string;
    uhawb: string | null;
    invoice_id: number | null;
    sync_status: 'draft' | 'pending' | 'success' | 'failed';
    sync_message: string | null;
    source: string;
    invoice?: {
        id: number;
        invoice_number: string | null;
        sync_status: string;
    } | null;
};

const props = defineProps<{
    shipments: {
        data: ShipmentRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { status: string; unlinked: boolean; q: string };
}>();

const selectedIds = ref<number[]>([]);
const search = ref(props.filters.q ?? '');

watch(
    () => props.filters.q,
    (value) => {
        search.value = value ?? '';
    },
);

const selectedCount = computed(() => selectedIds.value.length);

const trackingCount = computed(() => {
    const matches = search.value.match(/[^\s,;]+/g);

    return matches?.length ?? 0;
});

function statusVariant(status: string) {
    if (status === 'success') {
        return 'default' as const;
    }
    if (status === 'failed') {
        return 'destructive' as const;
    }
    if (status === 'draft') {
        return 'outline' as const;
    }
    return 'secondary' as const;
}

function applyFilters(
    overrides: Partial<{ status: string; unlinked: boolean; q: string }> = {},
) {
    selectedIds.value = [];

    const next = {
        status: props.filters.status,
        unlinked: props.filters.unlinked,
        q: search.value.trim(),
        ...overrides,
    };

    router.get(
        shipmentsIndex.url({
            query: {
                ...(next.status ? { status: next.status } : {}),
                ...(next.unlinked ? { unlinked: 1 } : {}),
                ...(next.q ? { q: next.q } : {}),
            },
        }),
        {},
        { preserveState: true, replace: true },
    );
}

function filterBy(status: string, unlinked = false) {
    applyFilters({ status, unlinked });
}

function clearSearch() {
    search.value = '';
    applyFilters({ q: '' });
}

function toggle(id: number, checked: boolean) {
    if (checked) {
        selectedIds.value = [...selectedIds.value, id];
    } else {
        selectedIds.value = selectedIds.value.filter((x) => x !== id);
    }
}

function toggleAll(checked: boolean) {
    if (checked) {
        selectedIds.value = props.shipments.data
            .filter((s) => !s.invoice_id)
            .map((s) => s.id);
    } else {
        selectedIds.value = [];
    }
}

function createInvoiceFromSelection() {
    if (selectedIds.value.length === 0) {
        return;
    }
    router.visit(
        createInvoice.url({
            query: { shipments: selectedIds.value.join(',') },
        }),
    );
}

const statusFilters = [
    { label: 'All', status: '', unlinked: false },
    { label: 'Unlinked drafts', status: 'draft', unlinked: true },
    { label: 'Draft', status: 'draft', unlinked: false },
    { label: 'Success', status: 'success', unlinked: false },
    { label: 'Failed', status: 'failed', unlinked: false },
];

function isActiveFilter(status: string, unlinked: boolean) {
    if (unlinked) {
        return props.filters.unlinked;
    }
    if (!status) {
        return !props.filters.status && !props.filters.unlinked;
    }
    return props.filters.status === status && !props.filters.unlinked;
}
</script>

<template>
    <Head title="Shipments" />

    <div class="flex min-h-full flex-col">
        <div class="border-b bg-muted/20 px-4 py-6 sm:px-6 lg:px-8">
            <div
                class="flex w-full flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
            >
                <Heading
                    title="Shipments"
                    description="Save drafts first, then select one or more to create a shared invoice for EPGL."
                />
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" as-child>
                        <Link :href="upload()">
                            <Upload class="size-4" />
                            Upload CSV
                        </Link>
                    </Button>
                    <Button as-child>
                        <Link :href="create()">
                            <Plus class="size-4" />
                            New shipment
                        </Link>
                    </Button>
                </div>
            </div>
        </div>

        <div class="flex w-full flex-1 flex-col gap-6 p-4 sm:p-6 lg:p-8">
            <div
                class="flex flex-col gap-3 rounded-xl border bg-card p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted"
                    >
                        <FileText class="size-4 text-muted-foreground" />
                    </div>
                    <div>
                        <p class="text-sm font-medium">
                            <template v-if="selectedCount">
                                {{ selectedCount }} shipment{{
                                    selectedCount === 1 ? '' : 's'
                                }}
                                selected
                            </template>
                            <template v-else>
                                Create invoice from selection
                            </template>
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Only unlinked drafts can be selected.
                        </p>
                    </div>
                </div>
                <Button
                    :disabled="!selectedCount"
                    @click="createInvoiceFromSelection"
                >
                    <FileText class="size-4" />
                    Create invoice
                </Button>
            </div>

            <div
                class="flex flex-col gap-4 rounded-xl border bg-card p-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="w-full max-w-2xl space-y-2">
                    <label
                        class="text-sm font-medium"
                        for="shipment-tracking-search"
                    >
                        Search tracking numbers
                    </label>
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-3 left-3 size-4 text-muted-foreground"
                        />
                        <textarea
                            id="shipment-tracking-search"
                            v-model="search"
                            rows="3"
                            class="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 dark:bg-input/30 flex w-full resize-y rounded-md border bg-transparent px-3 py-2 pl-9 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                            placeholder="Paste one or more tracking numbers — separated by commas, spaces, or new lines"
                            @keydown.meta.enter.prevent="applyFilters()"
                            @keydown.ctrl.enter.prevent="applyFilters()"
                        />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        <template v-if="trackingCount">
                            {{ trackingCount }} tracking number{{
                                trackingCount === 1 ? '' : 's'
                            }}
                            ready to search. Press Ctrl/⌘+Enter to search.
                        </template>
                        <template v-else>
                            Supports bulk lookup — paste a list of tracking
                            numbers at once.
                        </template>
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2 lg:pt-7">
                    <Button @click="applyFilters()">
                        <Search class="size-4" />
                        Search
                    </Button>
                    <Button
                        v-if="filters.q"
                        variant="outline"
                        @click="clearSearch"
                    >
                        <X class="size-4" />
                        Clear
                    </Button>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    v-for="item in statusFilters"
                    :key="item.label"
                    size="sm"
                    :variant="
                        isActiveFilter(item.status, item.unlinked)
                            ? 'default'
                            : 'outline'
                    "
                    @click="filterBy(item.status, item.unlinked)"
                >
                    {{ item.label }}
                </Button>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[960px] text-left text-sm">
                        <thead class="border-b bg-muted/40">
                            <tr class="text-muted-foreground">
                                <th class="w-12 px-4 py-3">
                                    <input
                                        type="checkbox"
                                        class="size-4 accent-primary"
                                        @change="
                                            toggleAll(
                                                (
                                                    $event.target as HTMLInputElement
                                                ).checked,
                                            )
                                        "
                                    />
                                </th>
                                <th class="px-4 py-3 font-medium">Tracking</th>
                                <th class="px-4 py-3 font-medium">Invoice</th>
                                <th class="px-4 py-3 font-medium">Source</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium">Message</th>
                                <th class="px-4 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-if="shipments.data.length === 0">
                                <td
                                    colspan="7"
                                    class="px-4 py-16 text-center text-muted-foreground"
                                >
                                    <Package
                                        class="mx-auto mb-3 size-10 opacity-30"
                                    />
                                    <p class="font-medium text-foreground">
                                        {{
                                            filters.q
                                                ? 'No matching shipments'
                                                : 'No shipments yet'
                                        }}
                                    </p>
                                    <p class="mt-1 text-sm">
                                        <template v-if="filters.q">
                                            Try different tracking numbers or
                                            clear the search.
                                        </template>
                                        <template v-else>
                                            Upload a CSV or create a shipment to
                                            get started.
                                        </template>
                                    </p>
                                </td>
                            </tr>
                            <tr
                                v-for="shipment in shipments.data"
                                :key="shipment.id"
                                class="transition-colors hover:bg-muted/30"
                                :class="{
                                    'bg-primary/5': selectedIds.includes(
                                        shipment.id,
                                    ),
                                }"
                            >
                                <td class="px-4 py-3">
                                    <input
                                        type="checkbox"
                                        class="size-4 accent-primary"
                                        :disabled="!!shipment.invoice_id"
                                        :checked="
                                            selectedIds.includes(shipment.id)
                                        "
                                        @change="
                                            toggle(
                                                shipment.id,
                                                (
                                                    $event.target as HTMLInputElement
                                                ).checked,
                                            )
                                        "
                                    />
                                </td>
                                <td
                                    class="px-4 py-3 font-medium tracking-tight font-mono"
                                >
                                    {{ shipment.tracking_number }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    <template v-if="shipment.invoice">
                                        {{
                                            shipment.invoice.invoice_number ||
                                            `#${shipment.invoice.id}`
                                        }}
                                    </template>
                                    <template v-else>
                                        <span class="text-muted-foreground/60"
                                            >—</span
                                        >
                                    </template>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        variant="secondary"
                                        class="rounded-md px-1.5 py-0 text-[10px] uppercase"
                                    >
                                        {{ shipment.source }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        :variant="
                                            statusVariant(shipment.sync_status)
                                        "
                                    >
                                        {{ shipment.sync_status }}
                                    </Badge>
                                </td>
                                <td
                                    class="max-w-md truncate px-4 py-3 text-muted-foreground"
                                >
                                    {{ shipment.sync_message || '—' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Button variant="ghost" size="sm" as-child>
                                        <Link :href="show(shipment.id)"
                                            >View</Link
                                        >
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                v-if="shipments.links.length > 3"
                class="flex flex-wrap gap-2"
            >
                <template v-for="(link, i) in shipments.links" :key="i">
                    <Button
                        v-if="link.url"
                        size="sm"
                        :variant="link.active ? 'default' : 'outline'"
                        as-child
                    >
                        <Link :href="link.url" v-html="link.label" />
                    </Button>
                </template>
            </div>
        </div>
    </div>
</template>
