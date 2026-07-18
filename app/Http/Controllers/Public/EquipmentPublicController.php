<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Services\EquipmentService;
use App\Services\SignedStorageUrlService;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class EquipmentPublicController extends Controller
{
    public function show(
        string $qrToken,
        EquipmentService $equipmentService,
        SignedStorageUrlService $signedUrls,
    ): View {
        $equipment = Equipment::query()
            ->with([
                'inspections' => fn ($q) => $q->latest('inspected_at')->limit(20),
                'maintenances' => fn ($q) => $q->latest('performed_at')->limit(20),
                'maintenanceSchedules',
                'documents',
                'openCheckout.worker',
                'openCheckout.zone',
            ])
            ->where('qr_token', $qrToken)
            ->firstOrFail();

        $payload = $equipmentService->toArray($equipment, includeRelations: false, canSeeIdentity: false);

        return view('public.equipment', [
            'equipment' => $payload,
            'inspections' => $equipment->inspections->map(fn ($row): array => [
                'inspected_at' => optional($row->inspected_at)?->toDateString(),
                'outcome' => $row->outcome->label(),
                'notes' => $row->notes,
            ])->values()->all(),
            'maintenances' => $equipment->maintenances->map(fn ($row): array => [
                'performed_at' => optional($row->performed_at)?->toDateString(),
                'type' => $row->maintenance_type->label(),
                'description' => $row->description,
            ])->values()->all(),
            'schedules' => $equipment->maintenanceSchedules->map(fn ($schedule): array => [
                'schedule_type' => $schedule->schedule_type->value,
                'interval_days' => $schedule->interval_days,
                'notes' => $schedule->notes,
            ])->values()->all(),
            'documents' => $equipment->documents->map(fn ($document): array => [
                'title' => $document->title,
                'url' => $signedUrls->temporaryUrl($document->file_path),
            ])->values()->all(),
        ]);
    }

    public function rejectWrite(): Response
    {
        return response('Method Not Allowed', 405);
    }
}
