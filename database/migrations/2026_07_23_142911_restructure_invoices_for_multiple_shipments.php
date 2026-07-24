<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        // Move existing invoice → shipment links to shipment.invoice_id
        $invoices = DB::table('invoices')->whereNotNull('shipment_id')->get(['id', 'shipment_id']);
        foreach ($invoices as $invoice) {
            DB::table('shipments')
                ->where('id', $invoice->shipment_id)
                ->update(['invoice_id' => $invoice->id]);
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipment_id');
            $table->string('tracking_number')->nullable()->change();
            $table->string('invoice_number')->nullable()->after('user_id');
        });

        // Backfill invoice_number from payload
        $rows = DB::table('invoices')->get(['id', 'payload']);
        foreach ($rows as $row) {
            $payload = json_decode($row->payload ?? '{}', true);
            $number = $payload['invoice']['invoiceNumber'] ?? null;
            if (is_string($number) && $number !== '') {
                DB::table('invoices')->where('id', $row->id)->update(['invoice_number' => $number]);
            }
        }

        // Existing pending rows that were never meant to auto-submit stay as-is;
        // mark unlinked non-success as draft for the new workflow.
        DB::table('shipments')
            ->whereNull('invoice_id')
            ->whereIn('sync_status', ['pending'])
            ->update([
                'sync_status' => 'draft',
                'sync_message' => 'Saved locally. Link to an invoice before submitting to EPGL.',
            ]);

        DB::table('invoices')
            ->where('sync_status', 'pending')
            ->update([
                'sync_status' => 'draft',
                'sync_message' => 'Draft invoice. Submit to EPGL when ready.',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->dropColumn('invoice_number');
        });

        $shipments = DB::table('shipments')->whereNotNull('invoice_id')->get(['id', 'invoice_id']);
        foreach ($shipments as $shipment) {
            DB::table('invoices')
                ->where('id', $shipment->invoice_id)
                ->whereNull('shipment_id')
                ->update(['shipment_id' => $shipment->id]);
        }

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
        });
    }
};
