<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import ShipmentController from '@/actions/App/Http/Controllers/Shipments/ShipmentController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, index } from '@/routes/shipments';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Shipments', href: index() },
            { title: 'New shipment', href: create() },
        ],
    },
});

function emptyItem() {
    return {
        description: '',
        countryOfOrigin: '',
        quantity: 1,
        hsCode: '',
        customsValue: { currencyCode: 'USD', amount: 0 },
        weight: { unit: 'KG', value: 0 },
        dimensions: { length: 0, width: 0, height: 0, unit: 'CM' },
    };
}

const form = useForm({
    trackingNumber: '',
    uhawb: '',
    sender: {
        name: '',
        email: '',
        phone: '',
        secondPhone: '',
        countryCode: '',
        state: '',
        postCode: '',
        city: '',
        line1: '',
        line2: '',
        line3: '',
    },
    receiver: {
        name: '',
        email: '',
        phone: '',
        secondPhone: '',
        countryCode: '',
        state: '',
        postCode: '',
        city: '',
        line1: '',
        line2: '',
        line3: '',
    },
    details: {
        weight: { unit: 'KG', value: 0 },
        declaredWeight: { unit: 'KG', value: 0 },
        cod: { currencyCode: 'USD', amount: 0 },
        customs: { currencyCode: 'USD', amount: 0 },
        deliveryCharges: { currencyCode: 'USD', amount: 0 },
        numberOfPieces: 1,
        pickupDate: new Date().toISOString().slice(0, 16),
        deliveryStatus: '',
        deliveryDate: '',
        deliveryAttempts: 0,
        shippingType: 'INT',
        productCategory: '',
        productType: 'Parcel',
        descriptionOfGoods: '',
        dimensions: { length: 0, width: 0, height: 0, unit: 'CM' },
    },
    items: [emptyItem()],
});

function submit() {
    form.transform((data) => ({
        ...data,
        details: {
            ...data.details,
            pickupDate: data.details.pickupDate
                ? new Date(data.details.pickupDate).toISOString()
                : null,
            deliveryDate: data.details.deliveryDate
                ? new Date(data.details.deliveryDate).toISOString()
                : null,
        },
        uhawb: data.uhawb || null,
    })).post(ShipmentController.store.url());
}
</script>

<template>
    <Head title="New shipment" />

    <div class="mx-auto flex max-w-5xl flex-col gap-8 p-4">
        <Heading
            title="Create shipment"
            description="Save shipment details locally. You must link them to an invoice before submitting to EPGL."
        />

        <form class="space-y-8" @submit.prevent="submit">
            <section class="space-y-4">
                <h2 class="text-lg font-medium">Tracking</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="trackingNumber">Tracking number</Label>
                        <Input
                            id="trackingNumber"
                            v-model="form.trackingNumber"
                            required
                        />
                        <InputError :message="form.errors.trackingNumber" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="uhawb">UHAWB (updates only)</Label>
                        <Input id="uhawb" v-model="form.uhawb" />
                        <InputError :message="form.errors.uhawb" />
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <h2 class="text-lg font-medium">Sender</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label>Name</Label>
                        <Input v-model="form.sender.name" required />
                        <InputError :message="form.errors['sender.name']" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Email</Label>
                        <Input v-model="form.sender.email" type="email" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Phone</Label>
                        <Input v-model="form.sender.phone" required />
                        <InputError :message="form.errors['sender.phone']" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Country code</Label>
                        <Input
                            v-model="form.sender.countryCode"
                            maxlength="2"
                            required
                            placeholder="US"
                        />
                        <InputError
                            :message="form.errors['sender.countryCode']"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>City</Label>
                        <Input v-model="form.sender.city" required />
                        <InputError :message="form.errors['sender.city']" />
                    </div>
                    <div class="grid gap-2">
                        <Label>State</Label>
                        <Input v-model="form.sender.state" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Post code</Label>
                        <Input v-model="form.sender.postCode" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label>Address line 1</Label>
                        <Input v-model="form.sender.line1" required />
                        <InputError :message="form.errors['sender.line1']" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Address line 2</Label>
                        <Input v-model="form.sender.line2" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Address line 3</Label>
                        <Input v-model="form.sender.line3" />
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <h2 class="text-lg font-medium">Receiver</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label>Name</Label>
                        <Input v-model="form.receiver.name" required />
                        <InputError :message="form.errors['receiver.name']" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Email</Label>
                        <Input v-model="form.receiver.email" type="email" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Phone</Label>
                        <Input v-model="form.receiver.phone" required />
                        <InputError :message="form.errors['receiver.phone']" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Country code</Label>
                        <Input
                            v-model="form.receiver.countryCode"
                            maxlength="2"
                            required
                            placeholder="GB"
                        />
                        <InputError
                            :message="form.errors['receiver.countryCode']"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>City</Label>
                        <Input v-model="form.receiver.city" required />
                        <InputError :message="form.errors['receiver.city']" />
                    </div>
                    <div class="grid gap-2">
                        <Label>State</Label>
                        <Input v-model="form.receiver.state" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Post code</Label>
                        <Input v-model="form.receiver.postCode" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label>Address line 1</Label>
                        <Input v-model="form.receiver.line1" required />
                        <InputError :message="form.errors['receiver.line1']" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Address line 2</Label>
                        <Input v-model="form.receiver.line2" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Address line 3</Label>
                        <Input v-model="form.receiver.line3" />
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <h2 class="text-lg font-medium">Shipment details</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-2">
                        <Label>Weight unit</Label>
                        <Input v-model="form.details.weight.unit" required />
                    </div>
                    <div class="grid gap-2">
                        <Label>Weight value</Label>
                        <Input
                            v-model.number="form.details.weight.value"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Pieces</Label>
                        <Input
                            v-model.number="form.details.numberOfPieces"
                            type="number"
                            min="1"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Declared weight unit</Label>
                        <Input
                            v-model="form.details.declaredWeight.unit"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Declared weight value</Label>
                        <Input
                            v-model.number="form.details.declaredWeight.value"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Shipping type</Label>
                        <Input
                            v-model="form.details.shippingType"
                            required
                            placeholder="INT or DOM"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Pickup date</Label>
                        <Input
                            v-model="form.details.pickupDate"
                            type="datetime-local"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Product category</Label>
                        <Input v-model="form.details.productCategory" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Product type</Label>
                        <Input v-model="form.details.productType" />
                    </div>
                    <div class="grid gap-2 sm:col-span-3">
                        <Label>Description of goods</Label>
                        <Input v-model="form.details.descriptionOfGoods" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Length</Label>
                        <Input
                            v-model.number="form.details.dimensions.length"
                            type="number"
                            step="0.01"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Width</Label>
                        <Input
                            v-model.number="form.details.dimensions.width"
                            type="number"
                            step="0.01"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Height</Label>
                        <Input
                            v-model.number="form.details.dimensions.height"
                            type="number"
                            step="0.01"
                        />
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-medium">Items</h2>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="form.items.push(emptyItem())"
                    >
                        <Plus class="size-4" />
                        Add item
                    </Button>
                </div>

                <div
                    v-for="(item, index) in form.items"
                    :key="index"
                    class="space-y-4 rounded-xl border p-4"
                >
                    <div class="flex items-center justify-between">
                        <h3 class="font-medium">Item {{ index + 1 }}</h3>
                        <Button
                            v-if="form.items.length > 1"
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="form.items.splice(index, 1)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2 sm:col-span-2">
                            <Label>Description</Label>
                            <Input v-model="item.description" required />
                        </div>
                        <div class="grid gap-2">
                            <Label>Country of origin</Label>
                            <Input
                                v-model="item.countryOfOrigin"
                                maxlength="2"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>Quantity</Label>
                            <Input
                                v-model.number="item.quantity"
                                type="number"
                                min="1"
                                required
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>HS code</Label>
                            <Input v-model="item.hsCode" />
                        </div>
                        <div class="grid gap-2">
                            <Label>Customs currency</Label>
                            <Input
                                v-model="item.customsValue.currencyCode"
                                maxlength="3"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>Customs amount</Label>
                            <Input
                                v-model.number="item.customsValue.amount"
                                type="number"
                                step="0.01"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label>Weight unit</Label>
                            <Input v-model="item.weight.unit" required />
                        </div>
                        <div class="grid gap-2">
                            <Label>Weight value</Label>
                            <Input
                                v-model.number="item.weight.value"
                                type="number"
                                step="0.01"
                                required
                            />
                        </div>
                    </div>
                </div>
                <InputError :message="form.errors.items" />
            </section>

            <Button type="submit" :disabled="form.processing">
                Save draft shipment
            </Button>
        </form>
    </div>
</template>
