<?php

namespace App\Http\Controllers\AuditLogs;

use App\Http\Controllers\Controller;
use App\Models\EpglAuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $action = $request->string('action')->toString();
        $status = $request->string('status')->toString();
        $search = $request->string('q')->toString();

        $logs = EpglAuditLog::query()
            ->with(['user:id,name,email', 'invoice:id,invoice_number', 'shipment:id,tracking_number'])
            ->when($action !== '' && in_array($action, [
                EpglAuditLog::ACTION_CREATE_SHIPMENT,
                EpglAuditLog::ACTION_ISSUE_INVOICE,
                EpglAuditLog::ACTION_AUTHENTICATE,
            ], true), fn ($q) => $q->where('action', $action))
            ->when($status === 'success', fn ($q) => $q->where('successful', true))
            ->when($status === 'failed', fn ($q) => $q->where('successful', false))
            ->when($search !== '', function ($q) use ($search): void {
                $term = '%'.mb_strtolower($search).'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->whereRaw('LOWER(COALESCE(tracking_number, \'\')) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(correlation_id, \'\')) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(message, \'\')) LIKE ?', [$term])
                        ->orWhereHas('user', function ($user) use ($term): void {
                            $user->whereRaw('LOWER(name) LIKE ?', [$term])
                                ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
                        });
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('audit-logs/Index', [
            'logs' => $logs,
            'filters' => [
                'action' => $action,
                'status' => $status,
                'q' => $search,
            ],
        ]);
    }

    public function show(EpglAuditLog $auditLog): Response
    {
        $auditLog->load([
            'user:id,name,email',
            'invoice:id,invoice_number,sync_status',
            'shipment:id,tracking_number,sync_status',
        ]);

        return Inertia::render('audit-logs/Show', [
            'log' => $auditLog,
        ]);
    }
}
