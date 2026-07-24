<?php

namespace App\Http\Controllers\Invoices;

use App\Actions\Invoices\SubmitInvoiceBatchToEpgl;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\LinkShipmentsRequest;
use App\Http\Requests\Invoices\StoreInvoiceRequest;
use App\Models\Invoice;
use App\Models\Shipment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();

        $invoices = Invoice::query()
            ->where('user_id', $request->user()->id)
            ->withCount('shipments')
            ->when($status !== '' && in_array($status, ['draft', 'pending', 'success', 'partial', 'failed'], true), fn ($q) => $q->where('sync_status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('invoices/Index', [
            'invoices' => $invoices,
            'filters' => [
                'status' => $status,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $preselected = collect(explode(',', $request->string('shipments')->toString()))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        $availableShipments = Shipment::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('invoice_id')
            ->whereIn('sync_status', [Shipment::STATUS_DRAFT, Shipment::STATUS_FAILED])
            ->latest()
            ->get(['id', 'tracking_number', 'uhawb', 'sync_status', 'source', 'payload']);

        return Inertia::render('invoices/Create', [
            'availableShipments' => $availableShipments->map(fn (Shipment $s) => [
                'id' => $s->id,
                'tracking_number' => $s->tracking_number,
                'uhawb' => $s->uhawb,
                'sync_status' => $s->sync_status,
                'source' => $s->source,
                'weight' => $this->shipmentWeight($s),
            ]),
            'preselectedIds' => $preselected,
            'draftInvoices' => Invoice::query()
                ->where('user_id', $request->user()->id)
                ->where('sync_status', Invoice::STATUS_DRAFT)
                ->withCount('shipments')
                ->latest()
                ->get(['id', 'invoice_number', 'sync_status', 'created_at']),
        ]);
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $payload = $request->payload();
        $shipmentIds = $request->shipmentIds();

        $invoice = Invoice::query()->create([
            'user_id' => $request->user()->id,
            'invoice_number' => $payload['invoice']['invoiceNumber'] ?? null,
            'tracking_number' => null,
            'payload' => $payload,
            'sync_status' => Invoice::STATUS_DRAFT,
            'sync_message' => 'Draft invoice. Submit to EPGL when ready.',
        ]);

        Shipment::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $shipmentIds)
            ->whereNull('invoice_id')
            ->update([
                'invoice_id' => $invoice->id,
                'sync_message' => 'Linked to invoice. Waiting for EPGL submit.',
            ]);

        if ($request->shouldSubmit()) {
            $invoice = SubmitInvoiceBatchToEpgl::make()->handle($invoice->fresh('shipments'));

            Inertia::flash('toast', [
                'type' => $invoice->sync_status === Invoice::STATUS_SUCCESS ? 'success' : 'error',
                'message' => $invoice->sync_message ?? 'Submit completed.',
            ]);
        } else {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Invoice saved as draft with :count shipment(s).', [
                    'count' => $invoice->shipments()->count(),
                ]),
            ]);
        }

        return to_route('invoices.show', $invoice);
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);

        $invoice->load('shipments');

        $linkableShipments = Shipment::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('invoice_id')
            ->whereIn('sync_status', [Shipment::STATUS_DRAFT, Shipment::STATUS_FAILED])
            ->latest()
            ->get(['id', 'tracking_number', 'uhawb', 'sync_status', 'source']);

        return Inertia::render('invoices/Show', [
            'invoice' => $invoice,
            'linkableShipments' => $linkableShipments,
            'canSubmit' => $invoice->canSubmitToEpgl(),
        ]);
    }

    public function link(LinkShipmentsRequest $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
        abort_unless($invoice->isDraft() || $invoice->isFailed(), 422, 'Only draft or failed invoices can accept more shipments.');

        $count = Shipment::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('id', $request->shipmentIds())
            ->whereNull('invoice_id')
            ->update([
                'invoice_id' => $invoice->id,
                'sync_status' => Shipment::STATUS_DRAFT,
                'sync_message' => 'Linked to invoice. Waiting for EPGL submit.',
            ]);

        if ($invoice->sync_status !== Invoice::STATUS_DRAFT) {
            $invoice->update([
                'sync_status' => Invoice::STATUS_DRAFT,
                'sync_message' => 'Shipments linked. Ready to submit to EPGL.',
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':count shipment(s) linked to invoice.', ['count' => $count]),
        ]);

        return to_route('invoices.show', $invoice);
    }

    public function submit(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_unless($invoice->user_id === $request->user()->id, 403);
        abort_unless($invoice->shipments()->exists(), 422, 'Link at least one shipment before submitting.');

        $invoice = SubmitInvoiceBatchToEpgl::make()->handle($invoice);

        Inertia::flash('toast', [
            'type' => $invoice->sync_status === Invoice::STATUS_SUCCESS ? 'success' : 'error',
            'message' => $invoice->sync_message ?? 'Submit completed.',
        ]);

        return to_route('invoices.show', $invoice);
    }

    public function retry(Request $request, Invoice $invoice): RedirectResponse
    {
        return $this->submit($request, $invoice);
    }

    /**
     * @return array{unit: string, value: float}
     */
    private function shipmentWeight(Shipment $shipment): array
    {
        $details = is_array($shipment->payload['details'] ?? null) ? $shipment->payload['details'] : [];
        $weight = is_array($details['declaredWeight'] ?? null)
            ? $details['declaredWeight']
            : (is_array($details['weight'] ?? null) ? $details['weight'] : []);

        return [
            'unit' => (string) ($weight['unit'] ?? 'KG'),
            'value' => (float) ($weight['value'] ?? 0),
        ];
    }
}
