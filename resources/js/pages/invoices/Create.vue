<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { FileText, Package, Plus, Send, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import InvoiceController from '@/actions/App/Http/Controllers/Invoices/InvoiceController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { create, show as showInvoice } from '@/routes/invoices';
import { index as shipmentsIndex } from '@/routes/shipments';

type AvailableShipment = {
    id: number;
    tracking_number: string;
    uhawb: string | null;
    sync_status: string;
    source: string;
    weight: { unit: string; value: number };
};

type DraftInvoice = {
    id: number;
    invoice_number: string | null;
    sync_status: string;
    shipments_count: number;
};

const props = defineProps<{
    availableShipments: AvailableShipment[];
    preselectedIds: number[];
    draftInvoices: DraftInvoice[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Shipments', href: shipmentsIndex() },
            { title: 'Create invoice', href: create() },
        ],
    },
});

function emptyCharge() {
    return {
        type: 'Base Rate',
        amount: { currencyCode: 'USD', amount: 0 },
    };
}

const selectedIds = ref<number[]>([...props.preselectedIds]);

const form = useForm({
    shipment_ids: [...props.preselectedIds] as number[],
    chargeableWeight: { unit: 'KG', value: 0 },
    charges: [emptyCharge()],
    invoice: {
        invoiceNumber: '',
        invoiceDate: new Date().toISOString().slice(0, 16),
        billingAccountNumber: '',
        billingAccountName: '',
        totalDiscountAmount: 0,
        taxAmount: 0,
        totalAmountIncludingTax: 0,
        currencyCode: 'USD',
    },
});

const selectedCount = computed(() => selectedIds.value.length);
const allSelected = computed(
    () =>
        props.availableShipments.length > 0 &&
        selectedIds.value.length === props.availableShipments.length,
);

const selectedShipments = computed(() =>
    props.availableShipments.filter((s) => selectedIds.value.includes(s.id)),
);

const totalSelectedWeight = computed(() =>
    selectedShipments.value.reduce((sum, s) => sum + (s.weight.value || 0), 0),
);

function syncSelection(id: number, checked: boolean) {
    if (checked) {
        selectedIds.value = [...selectedIds.value, id];
    } else {
        selectedIds.value = selectedIds.value.filter((x) => x !== id);
    }
    form.shipment_ids = [...selectedIds.value];
}

function selectAll(checked: boolean) {
    selectedIds.value = checked
        ? props.availableShipments.map((s) => s.id)
        : [];
    form.shipment_ids = [...selectedIds.value];
}

function save(submitAfter = false) {
    form.shipment_ids = [...selectedIds.value];
    form
        .transform((data) => ({
            ...data,
            submit_now: submitAfter,
            invoice: {
                ...data.invoice,
                invoiceDate: data.invoice.invoiceDate
                    ? new Date(data.invoice.invoiceDate).toISOString()
                    : null,
            },
        }))
        .post(InvoiceController.store.url(), {
            preserveScroll: true,
        });
}
</script>

<template>
    <Head title="Create invoice" />

    <div class="flex min-h-full flex-col">
        <div class="border-b bg-muted/20 px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto flex max-w-6xl flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <Heading
                    title="Create invoice"
                    description="Select shipments, enter shared billing details, then save a draft or submit to EPGL."
                />
                <div class="flex items-center gap-2 text-sm text-muted-foreground">
                    <Badge variant="outline" class="font-normal">
                        {{ selectedCount }} shipment{{
                            selectedCount === 1 ? '' : 's'
                        }}
                    </Badge>
                    <span v-if="selectedCount" class="hidden sm:inline">
                        · {{ totalSelectedWeight.toFixed(1) }} KG total
                    </span>
                </div>
            </div>
        </div>

        <div class="mx-auto grid w-full max-w-6xl flex-1 gap-6 p-4 sm:p-6 lg:grid-cols-12 lg:gap-8 lg:p-8">
            <!-- Shipments column -->
            <aside class="flex flex-col gap-4 lg:col-span-4">
                <div class="overflow-hidden rounded-xl border bg-card">
                    <div
                        class="flex items-center justify-between gap-3 border-b px-4 py-3"
                    >
                        <div class="flex items-center gap-2">
                            <div
                                class="flex size-8 items-center justify-center rounded-lg bg-muted"
                            >
                                <Package class="size-4 text-muted-foreground" />
                            </div>
                            <div>
                                <h2 class="text-sm font-semibold">Shipments</h2>
                                <p class="text-xs text-muted-foreground">
                                    Step 1 · select to invoice
                                </p>
                            </div>
                        </div>
                        <label
                            class="flex cursor-pointer items-center gap-2 text-xs font-medium text-muted-foreground hover:text-foreground"
                        >
                            <input
                                type="checkbox"
                                class="size-3.5 accent-primary"
                                :checked="allSelected"
                                @change="
                                    selectAll(
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                            />
                            All
                        </label>
                    </div>

                    <div
                        v-if="availableShipments.length === 0"
                        class="px-4 py-10 text-center"
                    >
                        <Package
                            class="mx-auto mb-3 size-8 text-muted-foreground/40"
                        />
                        <p class="text-sm text-muted-foreground">
                            No unlinked drafts.
                        </p>
                        <Button variant="link" class="mt-1 h-auto p-0" as-child>
                            <Link
                                :href="
                                    shipmentsIndex.url({
                                        query: { unlinked: 1 },
                                    })
                                "
                            >
                                Upload shipments
                            </Link>
                        </Button>
                    </div>

                    <div
                        v-else
                        class="max-h-[28rem] divide-y overflow-y-auto"
                    >
                        <label
                            v-for="shipment in availableShipments"
                            :key="shipment.id"
                            class="flex cursor-pointer items-start gap-3 px-4 py-3 transition-colors hover:bg-muted/40"
                            :class="{
                                'bg-primary/5': selectedIds.includes(
                                    shipment.id,
                                ),
                            }"
                        >
                            <input
                                type="checkbox"
                                class="mt-1 size-4 shrink-0 accent-primary"
                                :checked="selectedIds.includes(shipment.id)"
                                @change="
                                    syncSelection(
                                        shipment.id,
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                            />
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate font-medium tracking-tight"
                                >
                                    {{ shipment.tracking_number }}
                                </p>
                                <div
                                    class="mt-1 flex flex-wrap items-center gap-1.5"
                                >
                                    <Badge
                                        variant="secondary"
                                        class="rounded-md px-1.5 py-0 text-[10px] uppercase"
                                    >
                                        {{ shipment.source }}
                                    </Badge>
                                    <span class="text-xs text-muted-foreground">
                                        {{ shipment.weight.value }}
                                        {{ shipment.weight.unit }}
                                    </span>
                                </div>
                            </div>
                        </label>
                    </div>

                    <InputError
                        class="px-4 pb-2"
                        :message="form.errors.shipment_ids"
                    />
                </div>

                <div
                    v-if="draftInvoices.length"
                    class="rounded-xl border bg-card p-4"
                >
                    <p class="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Or link to draft
                    </p>
                    <ul class="space-y-2">
                        <li
                            v-for="draft in draftInvoices"
                            :key="draft.id"
                            class="flex items-center justify-between gap-2 rounded-lg bg-muted/40 px-3 py-2"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">
                                    {{
                                        draft.invoice_number ||
                                        `Invoice #${draft.id}`
                                    }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ draft.shipments_count }} shipment(s)
                                </p>
                            </div>
                            <Button variant="outline" size="sm" as-child>
                                <Link :href="showInvoice(draft.id)">Open</Link>
                            </Button>
                        </li>
                    </ul>
                </div>
            </aside>

            <!-- Invoice details column -->
            <div class="flex flex-col gap-6 lg:col-span-8">
                <section class="overflow-hidden rounded-xl border bg-card">
                    <div class="flex items-center gap-3 border-b px-5 py-4">
                        <div
                            class="flex size-8 items-center justify-center rounded-lg bg-muted"
                        >
                            <FileText class="size-4 text-muted-foreground" />
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold">
                                Invoice details
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                Step 2 · shared across selected shipments
                            </p>
                        </div>
                    </div>

                    <div class="space-y-8 p-5">
                        <div>
                            <h3
                                class="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Weight defaults
                            </h3>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label>Unit</Label>
                                    <Input
                                        v-model="form.chargeableWeight.unit"
                                        required
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label>Value</Label>
                                    <Input
                                        v-model.number="
                                            form.chargeableWeight.value
                                        "
                                        type="number"
                                        step="0.01"
                                        min="0"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        Leave 0 to use each shipment’s own
                                        weight.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <Separator />

                        <div>
                            <div
                                class="mb-3 flex items-center justify-between gap-2"
                            >
                                <h3
                                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    Charges
                                </h3>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="form.charges.push(emptyCharge())"
                                >
                                    <Plus class="size-3.5" />
                                    Add
                                </Button>
                            </div>

                            <div class="space-y-3">
                                <div
                                    v-for="(charge, index) in form.charges"
                                    :key="index"
                                    class="grid gap-3 rounded-lg border bg-muted/20 p-4 sm:grid-cols-[1fr_5rem_1fr_auto]"
                                >
                                    <div class="grid gap-2">
                                        <Label class="text-xs">Type</Label>
                                        <Input
                                            v-model="charge.type"
                                            required
                                        />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label class="text-xs">Currency</Label>
                                        <Input
                                            v-model="
                                                charge.amount.currencyCode
                                            "
                                            maxlength="3"
                                            required
                                        />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label class="text-xs">Amount</Label>
                                        <Input
                                            v-model.number="
                                                charge.amount.amount
                                            "
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            required
                                        />
                                    </div>
                                    <div class="flex items-end">
                                        <Button
                                            v-if="form.charges.length > 1"
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="text-muted-foreground hover:text-destructive"
                                            @click="
                                                form.charges.splice(index, 1)
                                            "
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <Separator />

                        <div>
                            <h3
                                class="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                Billing
                            </h3>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label>Invoice number</Label>
                                    <Input
                                        v-model="form.invoice.invoiceNumber"
                                        required
                                        placeholder="INV-001"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label>Invoice date</Label>
                                    <Input
                                        v-model="form.invoice.invoiceDate"
                                        type="datetime-local"
                                        required
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label>Billing account number</Label>
                                    <Input
                                        v-model="
                                            form.invoice.billingAccountNumber
                                        "
                                        required
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label>Billing account name</Label>
                                    <Input
                                        v-model="
                                            form.invoice.billingAccountName
                                        "
                                        required
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label>Total discount</Label>
                                    <Input
                                        v-model.number="
                                            form.invoice.totalDiscountAmount
                                        "
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        required
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label>Tax amount</Label>
                                    <Input
                                        v-model.number="form.invoice.taxAmount"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        required
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label>Total including tax</Label>
                                    <Input
                                        v-model.number="
                                            form.invoice
                                                .totalAmountIncludingTax
                                        "
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        required
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label>Currency</Label>
                                    <Input
                                        v-model="form.invoice.currencyCode"
                                        maxlength="3"
                                        required
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div
                    class="sticky bottom-4 z-10 flex flex-col gap-3 rounded-xl border bg-background/95 p-4 shadow-sm backdrop-blur supports-backdrop-filter:bg-background/80 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="text-sm text-muted-foreground">
                        <template v-if="selectedCount">
                            Ready to invoice
                            <span class="font-medium text-foreground">{{
                                selectedCount
                            }}</span>
                            shipment{{ selectedCount === 1 ? '' : 's' }}
                        </template>
                        <template v-else>
                            Select at least one shipment to continue
                        </template>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="form.processing || !selectedCount"
                            @click="save(false)"
                        >
                            Save draft
                        </Button>
                        <Button
                            type="button"
                            :disabled="form.processing || !selectedCount"
                            @click="save(true)"
                        >
                            <Send class="size-4" />
                            Submit to EPGL
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
