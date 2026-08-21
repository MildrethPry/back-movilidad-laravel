<?php

namespace Domain\Alerts\Actions;

use Carbon\Carbon;
use Domain\Alerts\Models\Alert;
use Domain\Auth\Models\DriverLicense;
use Domain\Requests\Models\RouteSheet;
use Domain\Requests\Models\ServiceStation;
use Domain\Vehicles\Models\Vehicle;
use Domain\Vehicles\Models\VehicleLegalDocument;

class GenerateAlertsAction
{
    /** Días que una alerta permanece visible antes de caducar. */
    public const ALERT_LIFETIME_DAYS = 5;

    public function execute(): void
    {
        $limit = Carbon::today()->addDays(30);

        foreach (DriverLicense::with('driver.user')->get() as $license) {
            $exp = Carbon::parse($license->expiration_date);
            if ($exp->lte($limit)) {
                $this->upsert(
                    key: 'licencia:'.$license->id,
                    type: 'licencia',
                    severity: $exp->isPast() ? 'alta' : 'media',
                    title: 'Vencimiento de licencia',
                    message: trim(
                        ($license->driver?->user?->first_name ?? '').' '.($license->driver?->user?->last_name ?? '')
                    ).' · vence '.$exp->toDateString(),
                    route: '/app/secretaria/flota/conductores',
                    entityId: $license->id,
                );
            }
        }

        foreach (VehicleLegalDocument::with('vehicle')->get() as $doc) {
            if (! $doc->expiration_date) {
                continue;
            }
            $exp = Carbon::parse($doc->expiration_date);
            if ($exp->lte($limit)) {
                $this->upsert(
                    key: 'matricula:'.$doc->id,
                    type: 'matricula',
                    severity: $exp->isPast() ? 'alta' : 'media',
                    title: 'Vencimiento documental de vehículo',
                    message: ($doc->vehicle?->plate ?? '').' · '.$doc->document_type.' · '.$exp->toDateString(),
                    route: '/app/secretaria/flota/vehiculos',
                    entityId: $doc->id,
                );
            }
        }

        foreach (Vehicle::all() as $vehicle) {
            if ($vehicle->current_mileage >= $vehicle->next_oil_change_mileage) {
                $this->upsert(
                    key: 'mantenimiento:'.$vehicle->id,
                    type: 'mantenimiento',
                    severity: 'alta',
                    title: 'Mantenimiento por km vencido',
                    message: $vehicle->plate.' · '.$vehicle->current_mileage.' / '.$vehicle->next_oil_change_mileage.' km',
                    route: '/app/secretaria/taller',
                    entityId: $vehicle->id,
                );
            }
        }

        foreach (ServiceStation::where('active_agreement', true)->get() as $station) {
            if ($station->monthly_quota_liters && $station->monthly_quota_liters > 0) {
                $remaining = (float) $station->monthly_quota_liters - (float) $station->consumed_liters;
                $threshold = (float) $station->monthly_quota_liters * 0.2;
                if ($remaining <= $threshold) {
                    $this->upsert(
                        key: 'cupo:'.$station->id,
                        type: 'cupo_combustible',
                        severity: 'media',
                        title: 'Cupo de combustible bajo',
                        message: $station->commercial_name.' · quedan '.$remaining.' L',
                        route: '/app/secretaria/gasolineras',
                        entityId: $station->id,
                    );
                }
            }
        }

        foreach (RouteSheet::with('request')->where('driver_response', 'rechazado')->get() as $sheet) {
            $this->upsert(
                key: 'rechazo:'.$sheet->id,
                type: 'rechazo_conductor',
                severity: 'alta',
                title: 'Conductor rechazó asignación',
                message: ($sheet->request?->destination ?? '').' · hoja #'.$sheet->id,
                route: '/app/secretaria/reasignar',
                entityId: $sheet->id,
            );
        }

        $this->prune();
    }

    private function upsert(
        string $key,
        string $type,
        string $severity,
        string $title,
        string $message,
        ?string $route,
        ?int $entityId,
    ): void {
        Alert::query()->updateOrCreate(
            ['key' => $key],
            [
                'type' => $type,
                'severity' => $severity,
                'title' => $title,
                'message' => $message,
                'route' => $route,
                'entity_id' => $entityId,
            ]
        );
    }

    private function prune(): void
    {
        Alert::query()
            ->where('created_at', '<', Carbon::now()->subDays(self::ALERT_LIFETIME_DAYS))
            ->delete();
    }
}
