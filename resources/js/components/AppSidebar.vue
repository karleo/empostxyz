<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ClipboardList, FileText, LayoutGrid, Package, Settings2 } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { edit as editApiSettings } from '@/routes/api-settings';
import { index as auditLogsIndex } from '@/routes/audit-logs';
import { index as invoicesIndex } from '@/routes/invoices';
import { index as shipmentsIndex } from '@/routes/shipments';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Shipments',
        href: shipmentsIndex(),
        icon: Package,
    },
    {
        title: 'Invoices',
        href: invoicesIndex(),
        icon: FileText,
    },
    {
        title: 'Audit logs',
        href: auditLogsIndex(),
        icon: ClipboardList,
    },
    {
        title: 'API Settings',
        href: editApiSettings(),
        icon: Settings2,
    },
];

const footerNavItems: NavItem[] = [];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
