<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ShipmentController from '@/actions/App/Http/Controllers/Shipments/ShipmentController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, template, upload } from '@/routes/shipments';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Shipments', href: index() },
            { title: 'Upload CSV', href: upload() },
        ],
    },
});
</script>

<template>
    <Head title="Upload shipments" />

    <div class="mx-auto flex max-w-2xl flex-col gap-6 p-4">
        <Heading
            title="Upload shipments CSV"
            description="Import shipments as local drafts. Select them afterward, create one invoice for many shipments, then submit to EPGL."
        />

        <div class="rounded-xl border p-4 text-sm text-muted-foreground">
            <p>
                Download the template, fill one shipment per row (with one item),
                then upload the file. Duplicate tracking numbers (already in the
                system or repeated in the file) are skipped and you will be
                alerted.
            </p>
            <Button class="mt-3" variant="outline" as-child>
                <a :href="template.url()" download>Download CSV template</a>
            </Button>
        </div>

        <Form
            v-bind="ShipmentController.upload.form()"
            enctype="multipart/form-data"
            class="space-y-4"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="file">CSV file</Label>
                <Input
                    id="file"
                    name="file"
                    type="file"
                    accept=".csv,text/csv"
                    required
                />
                <InputError :message="errors.file" />
            </div>

            <div class="flex gap-2">
                <Button type="submit" :disabled="processing">
                    Upload drafts
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="index()">Cancel</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
