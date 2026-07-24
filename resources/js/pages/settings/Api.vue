<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import ApiSettingsController from '@/actions/App/Http/Controllers/Settings/ApiSettingsController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/api-settings';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'API settings',
                href: edit(),
            },
        ],
    },
});

defineProps<{
    settings: {
        base_url: string;
        client_id: string;
        has_client_secret: boolean;
        is_configured: boolean;
    };
}>();
</script>

<template>
    <Head title="API settings" />

    <h1 class="sr-only">API settings</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="EPGL API"
            description="Configure the API base URL and authentication credentials used for shipments and invoices."
        />

        <p
            v-if="!settings.is_configured"
            class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100"
        >
            API is not configured yet. Save credentials before creating shipments
            or invoices.
        </p>

        <Form
            v-bind="ApiSettingsController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="base_url">API base URL</Label>
                <Input
                    id="base_url"
                    name="base_url"
                    type="url"
                    :default-value="settings.base_url"
                    required
                    placeholder="https://api-stg.epgl.ae"
                />
                <p class="text-sm text-muted-foreground">
                    Host only, e.g.
                    <code class="text-xs">https://api-stg.epgl.ae</code>
                    — not the full
                    <code class="text-xs">/api/v1/auth/authenticate</code>
                    path. The app adds API paths automatically.
                </p>
                <InputError :message="errors.base_url" />
            </div>

            <div class="grid gap-2">
                <Label for="client_id">Client ID</Label>
                <Input
                    id="client_id"
                    name="client_id"
                    :default-value="settings.client_id"
                    required
                    autocomplete="off"
                    placeholder="Your EPGL client ID"
                />
                <InputError :message="errors.client_id" />
            </div>

            <div class="grid gap-2">
                <Label for="client_secret">Client secret</Label>
                <Input
                    id="client_secret"
                    name="client_secret"
                    type="password"
                    autocomplete="new-password"
                    :placeholder="
                        settings.has_client_secret
                            ? 'Leave blank to keep current secret'
                            : 'Your EPGL client secret'
                    "
                />
                <p class="text-sm text-muted-foreground">
                    Stored encrypted. Leave blank when updating if you do not
                    want to change it.
                </p>
                <InputError :message="errors.client_secret" />
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <Button
                    type="submit"
                    :disabled="processing"
                    data-test="save-api-settings"
                >
                    Save
                </Button>
            </div>
        </Form>

        <Form
            v-bind="ApiSettingsController.test.form()"
            class="space-y-4 border-t pt-6"
            v-slot="{ errors, processing }"
        >
            <Heading
                variant="small"
                title="Test connection"
                description="Authenticate against EPGL using the values below (does not save)."
            />

            <div class="grid gap-2">
                <Label for="test_base_url">API base URL</Label>
                <Input
                    id="test_base_url"
                    name="base_url"
                    type="url"
                    :default-value="settings.base_url"
                    required
                    placeholder="https://api-stg.epgl.ae"
                />
                <p class="text-sm text-muted-foreground">
                    Use
                    <code class="text-xs">https://api-stg.epgl.ae</code>
                    (host only).
                </p>
                <InputError :message="errors.base_url" />
            </div>

            <div class="grid gap-2">
                <Label for="test_client_id">Client ID</Label>
                <Input
                    id="test_client_id"
                    name="client_id"
                    :default-value="settings.client_id"
                    required
                />
                <InputError :message="errors.client_id" />
            </div>

            <div class="grid gap-2">
                <Label for="test_client_secret">Client secret</Label>
                <Input
                    id="test_client_secret"
                    name="client_secret"
                    type="password"
                    :placeholder="
                        settings.has_client_secret
                            ? 'Leave blank to use saved secret'
                            : 'Required'
                    "
                />
                <InputError :message="errors.client_secret" />
            </div>

            <Button
                type="submit"
                variant="outline"
                :disabled="processing"
                data-test="test-api-settings"
            >
                Test connection
            </Button>
        </Form>
    </div>
</template>
