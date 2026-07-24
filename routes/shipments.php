<?php

use App\Http\Controllers\AuditLogs\AuditLogController;
use App\Http\Controllers\Invoices\InvoiceController;
use App\Http\Controllers\Shipments\ShipmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('shipments', [ShipmentController::class, 'index'])->name('shipments.index');
    Route::get('shipments/create', [ShipmentController::class, 'create'])->name('shipments.create');
    Route::post('shipments', [ShipmentController::class, 'store'])->name('shipments.store');
    Route::get('shipments/upload', [ShipmentController::class, 'showUpload'])->name('shipments.upload');
    Route::post('shipments/upload', [ShipmentController::class, 'upload'])->name('shipments.upload.store');
    Route::get('shipments/template', [ShipmentController::class, 'template'])->name('shipments.template');
    Route::get('shipments/{shipment}', [ShipmentController::class, 'show'])->name('shipments.show');

    Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::post('invoices/{invoice}/link', [InvoiceController::class, 'link'])->name('invoices.link');
    Route::post('invoices/{invoice}/submit', [InvoiceController::class, 'submit'])->name('invoices.submit');
    Route::post('invoices/{invoice}/retry', [InvoiceController::class, 'retry'])->name('invoices.retry');

    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
});
