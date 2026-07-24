<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ClipboardList, Search } from '@lucide/vue';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as auditLogsIndex, show } from '@/routes/audit-logs';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Audit logs',
                href: auditLogsIndex(),
            },
        ],
    },
});

type LogRow = {
    id: number;
    action: string;
    endpoint: string;
    tracking_number: string | null;
    http_status: number | null;
    successful: boolean;
    message: string | null;
    correlation_id: string | null;
    created_at: string;
    user?: { id: number; name: string; email: string } | null;
    invoice?: { id: number; invoice_number: string | null } | null;
};

const props = defineProps<{
    logs: {
        data: LogRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { action: string; status: string; q: string };
}>();

const search = ref(props.filters.q);

watch(
    () => props.filters.q,
    (value) => {
        search.value = value;
    },
);

function applyFilters(overrides: Partial<typeof props.filters> = {}) {
    const next = {
        action: props.filters.action,
        status: props.filters.status,
        q: search.value,
        ...overrides,
    };

    router.get(
        auditLogsIndex.url({
            query: Object.fromEntries(
                Object.entries(next).filter(([, value]) => value !== ''),
            ),
        }),
        {},
        { preserveState: true, replace: true },
    );
}

function actionLabel(action: string) {
    if (action === 'create_shipment') {
        return 'Create shipment';
    }
    if (action === 'issue_invoice') {
        return 'Issue invoice';
    }
    if (action === 'authenticate') {
        return 'Authenticate';
    }
    return action;
}
</script>

<template>
    <Head title="Audit logs" />

    <div class="flex min-h-full flex-col">
        <div class="border-b bg-muted/20 px-4 py-6 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-6xl">
                <Heading
                    title="EPGL audit logs"
                    description="Request and response history for every Empost submission, including who submitted."
                />
            </div>
        </div>

        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6 lg:p-8">
            <div
                class="flex flex-col gap-3 rounded-xl border bg-card p-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="relative w-full max-w-md">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        class="pl-9"
                        placeholder="Search tracking, user, correlation ID…"
                        @keydown.enter.prevent="applyFilters()"
                    />
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        size="sm"
                        :variant="!filters.action ? 'default' : 'outline'"
                        @click="applyFilters({ action: '' })"
                    >
                        All actions
                    </Button>
                    <Button
                        size="sm"
                        :variant="
                            filters.action === 'create_shipment'
                                ? 'default'
                                : 'outline'
                        "
                        @click="applyFilters({ action: 'create_shipment' })"
                    >
                        Shipments
                    </Button>
                    <Button
                        size="sm"
                        :variant="
                            filters.action === 'issue_invoice'
                                ? 'default'
                                : 'outline'
                        "
                        @click="applyFilters({ action: 'issue_invoice' })"
                    >
                        Invoices
                    </Button>
                    <Button
                        size="sm"
                        :variant="
                            filters.status === 'success' ? 'default' : 'outline'
                        "
                        @click="
                            applyFilters({
                                status:
                                    filters.status === 'success'
                                        ? ''
                                        : 'success',
                            })
                        "
                    >
                        Success
                    </Button>
                    <Button
                        size="sm"
                        :variant="
                            filters.status === 'failed' ? 'default' : 'outline'
                        "
                        @click="
                            applyFilters({
                                status:
                                    filters.status === 'failed' ? '' : 'failed',
                            })
                        "
                    >
                        Failed
                    </Button>
                    <Button size="sm" variant="secondary" @click="applyFilters()">
                        Search
                    </Button>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border bg-card">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[960px] text-left text-sm">
                        <thead class="border-b bg-muted/40">
                            <tr class="text-muted-foreground">
                                <th class="px-4 py-3 font-medium">When</th>
                                <th class="px-4 py-3 font-medium">Submitted by</th>
                                <th class="px-4 py-3 font-medium">Action</th>
                                <th class="px-4 py-3 font-medium">Tracking</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium">Message</th>
                                <th class="px-4 py-3 font-medium"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-if="logs.data.length === 0">
                                <td
                                    colspan="7"
                                    class="px-4 py-16 text-center text-muted-foreground"
                                >
                                    <ClipboardList
                                        class="mx-auto mb-3 size-10 opacity-30"
                                    />
                                    <p class="font-medium text-foreground">
                                        No audit logs yet
                                    </p>
                                    <p class="mt-1 text-sm">
                                        Logs appear after you submit invoices to
                                        EPGL.
                                    </p>
                                </td>
                            </tr>
                            <tr
                                v-for="log in logs.data"
                                :key="log.id"
                                class="transition-colors hover:bg-muted/30"
                            >
                                <td class="px-4 py-3 whitespace-nowrap text-muted-foreground">
                                    {{
                                        new Date(log.created_at).toLocaleString()
                                    }}
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="log.user">
                                        <p class="font-medium">
                                            {{ log.user.name }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ log.user.email }}
                                        </p>
                                    </div>
                                    <span v-else class="text-muted-foreground"
                                        >System</span
                                    >
                                </td>
                                <td class="px-4 py-3">
                                    <Badge variant="secondary">
                                        {{ actionLabel(log.action) }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 font-medium tracking-tight">
                                    {{ log.tracking_number || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        :variant="
                                            log.successful
                                                ? 'default'
                                                : 'destructive'
                                        "
                                    >
                                        {{
                                            log.successful
                                                ? 'success'
                                                : 'failed'
                                        }}
                                        <template v-if="log.http_status">
                                            · {{ log.http_status }}
                                        </template>
                                    </Badge>
                                </td>
                                <td
                                    class="max-w-xs truncate px-4 py-3 text-muted-foreground"
                                >
                                    {{ log.message || '—' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Button variant="ghost" size="sm" as-child>
                                        <Link :href="show(log.id)">View</Link>
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                v-if="logs.links.length > 3"
                class="flex flex-wrap gap-2"
            >
                <template v-for="(link, i) in logs.links" :key="i">
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
