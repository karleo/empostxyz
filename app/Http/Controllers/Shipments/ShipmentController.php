<?php

namespace App\Http\Controllers\Shipments;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shipments\StoreShipmentRequest;
use App\Http\Requests\Shipments\UploadShipmentsRequest;
use App\Models\Shipment;
use App\Services\Shipments\CsvShipmentImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShipmentController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $search = $request->string('q')->toString();
        $trackingNumbers = collect(preg_split('/[\s,;]+/u', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn (string $value): string => trim($value))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $shipments = Shipment::query()
            ->where('user_id', $request->user()->id)
            ->with('invoice:id,invoice_number,sync_status')
            ->when($status !== '' && in_array($status, ['draft', 'pending', 'success', 'failed'], true), fn ($q) => $q->where('sync_status', $status))
            ->when($request->boolean('unlinked'), fn ($q) => $q->whereNull('invoice_id'))
            ->when($trackingNumbers !== [], function ($q) use ($trackingNumbers): void {
                $q->where(function ($inner) use ($trackingNumbers): void {
                    foreach ($trackingNumbers as $trackingNumber) {
                        $inner->orWhere('tracking_number', 'like', '%'.$trackingNumber.'%');
                    }
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('shipments/Index', [
            'shipments' => $shipments,
            'filters' => [
                'status' => $status,
                'unlinked' => $request->boolean('unlinked'),
                'q' => $search,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('shipments/Create');
    }

    public function store(StoreShipmentRequest $request): RedirectResponse
    {
        $payload = $request->payload();

        $shipment = Shipment::query()->create([
            'user_id' => $request->user()->id,
            'tracking_number' => $payload['trackingNumber'],
            'uhawb' => $payload['uhawb'] ?? null,
            'payload' => $payload,
            'sync_status' => Shipment::STATUS_DRAFT,
            'sync_message' => 'Saved locally. Link to an invoice before submitting to EPGL.',
            'source' => 'form',
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Shipment saved as draft. Create or link an invoice to submit to EPGL.'),
        ]);

        return to_route('shipments.index', ['unlinked' => 1]);
    }

    public function show(Request $request, Shipment $shipment): Response
    {
        abort_unless($shipment->user_id === $request->user()->id, 403);

        $shipment->load('invoice');

        return Inertia::render('shipments/Show', [
            'shipment' => $shipment,
        ]);
    }

    public function showUpload(): Response
    {
        return Inertia::render('shipments/Upload');
    }

    public function upload(UploadShipmentsRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $path = $file->getRealPath();

        try {
            $rows = (new CsvShipmentImporter)->import($path);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $seenInFile = [];
        $duplicatesInFile = [];
        $uniqueRows = [];

        foreach ($rows as $row) {
            $tracking = $row['tracking_number'];

            if (isset($seenInFile[$tracking])) {
                $duplicatesInFile[$tracking] = true;

                continue;
            }

            $seenInFile[$tracking] = true;
            $uniqueRows[] = $row;
        }

        $trackingNumbers = array_column($uniqueRows, 'tracking_number');
        $existing = Shipment::query()
            ->whereIn('tracking_number', $trackingNumbers)
            ->pluck('tracking_number')
            ->all();
        $existingLookup = array_fill_keys($existing, true);

        $duplicatesExisting = [];
        $created = 0;

        foreach ($uniqueRows as $row) {
            $tracking = $row['tracking_number'];

            if (isset($existingLookup[$tracking])) {
                $duplicatesExisting[$tracking] = true;

                continue;
            }

            Shipment::query()->create([
                'user_id' => $request->user()->id,
                'tracking_number' => $tracking,
                'uhawb' => $row['uhawb'],
                'payload' => $row['payload'],
                'sync_status' => Shipment::STATUS_DRAFT,
                'sync_message' => 'Saved locally. Link to an invoice before submitting to EPGL.',
                'source' => 'csv',
            ]);
            $created++;
            $existingLookup[$tracking] = true;
        }

        $duplicateTrackings = array_values(array_unique([
            ...array_keys($duplicatesInFile),
            ...array_keys($duplicatesExisting),
        ]));

        if ($duplicateTrackings !== [] && $created === 0) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Duplicate tracking number(s) found — nothing was uploaded: :trackings', [
                    'trackings' => implode(', ', $duplicateTrackings),
                ]),
            ]);

            return back()->withErrors([
                'file' => __('Duplicate tracking number(s): :trackings. These were not uploaded.', [
                    'trackings' => implode(', ', $duplicateTrackings),
                ]),
            ]);
        }

        if ($duplicateTrackings !== []) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __(':created uploaded. Skipped duplicate tracking number(s): :trackings', [
                    'created' => $created,
                    'trackings' => implode(', ', $duplicateTrackings),
                ]),
            ]);

            return to_route('shipments.index', ['unlinked' => 1])->withErrors([
                'file' => __('Skipped duplicate tracking number(s): :trackings', [
                    'trackings' => implode(', ', $duplicateTrackings),
                ]),
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':count shipment(s) saved as draft. Select them and create an invoice to submit to EPGL.', [
                'count' => $created,
            ]),
        ]);

        return to_route('shipments.index', ['unlinked' => 1]);
    }

    public function template(): StreamedResponse
    {
        $headers = CsvShipmentImporter::headers();

        return response()->streamDownload(function () use ($headers): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            fputcsv($out, [
                'TRACK1234567890',
                '',
                'John Doe',
                'john.doe@sender.com',
                '+11234567890',
                '',
                'US',
                'California',
                '90210',
                'Beverly Hills',
                '1234 Elm Street',
                'Apt 101',
                '',
                'Jane Smith',
                'jane.smith@receiver.com',
                '+442071234567',
                '',
                'GB',
                'London',
                'SW1A 1AA',
                'London',
                '10 Downing Street',
                '',
                '',
                'KG',
                '10',
                'KG',
                '10',
                '1',
                now()->toIso8601String(),
                'INT',
                'Electronics',
                'Parcel',
                'Smartphones',
                '50',
                '30',
                '20',
                'CM',
                'iPhone 14',
                'CN',
                '1',
                '851712',
                'USD',
                '1500',
                'KG',
                '1.5',
            ]);
            fclose($out);
        }, 'shipment-template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
