<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Domain\Auth\Models\DriverLicense;
use Domain\Requests\Models\RouteSheet;
use Domain\Requests\Models\ServiceStation;
use Domain\Vehicles\Models\Vehicle;
use Domain\Vehicles\Models\VehicleLegalDocument;
use Illuminate\Http\Request;

class AlertsController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (! $user->hasRole(['secretaria', 'jefe_transporte', 'conductor', 'chofer', 'mecanico'])) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $alerts = [];
        $limit = Carbon::today()->addDays(30);

        foreach (DriverLicense::with('driver.user')->get() as $license) {
            $exp = Carbon::parse($license->expiration_date);
            if ($exp->lte($limit)) {
                $alerts[] = [
                    'type' => 'licencia',
                    'severity' => $exp->isPast() ? 'alta' : 'media',
                    'title' => 'Vencimiento de licencia',
                    'message' => optional($license->driver?->user)->first_name.' '.optional($license->driver?->user)->last_name
                        .' · vence '.$exp->toDateString(),
                ];
            }
        }

        foreach (VehicleLegalDocument::with('vehicle')->get() as $doc) {
            if (! $doc->expiration_date) {
                continue;
            }
            $exp = Carbon::parse($doc->expiration_date);
            if ($exp->lte($limit)) {
                $alerts[] = [
                    'type' => 'matricula',
                    'severity' => $exp->isPast() ? 'alta' : 'media',
                    'title' => 'Vencimiento documental de vehículo',
                    'message' => optional($doc->vehicle)->plate.' · '.$doc->document_type.' · '.$exp->toDateString(),
                    'blocks_assignment' => $exp->isPast(),
                ];
            }
        }

        foreach (Vehicle::all() as $vehicle) {
            if ($vehicle->current_mileage >= $vehicle->next_oil_change_mileage) {
                $alerts[] = [
                    'type' => 'mantenimiento',
                    'severity' => 'alta',
                    'title' => 'Mantenimiento por km vencido',
                    'message' => $vehicle->plate.' · '.$vehicle->current_mileage.' / '.$vehicle->next_oil_change_mileage.' km',
                ];
            }
        }

        foreach (ServiceStation::where('active_agreement', true)->get() as $station) {
            if ($station->monthly_quota_liters && $station->monthly_quota_liters > 0) {
                $remaining = (float) $station->monthly_quota_liters - (float) $station->consumed_liters;
                $threshold = (float) $station->monthly_quota_liters * 0.2;
                if ($remaining <= $threshold) {
                    $alerts[] = [
                        'type' => 'cupo_combustible',
                        'severity' => 'media',
                        'title' => 'Cupo de combustible bajo',
                        'message' => $station->commercial_name.' · quedan '.$remaining.' L',
                    ];
                }
            }
        }

        $rejected = RouteSheet::with(['driver.user', 'request'])
            ->where('driver_response', 'rechazado')
            ->latest()
            ->limit(20)
            ->get();

        foreach ($rejected as $sheet) {
            $alerts[] = [
                'type' => 'rechazo_conductor',
                'severity' => 'alta',
                'title' => 'Conductor rechazó asignación',
                'message' => optional($sheet->request)->destination.' · hoja #'.$sheet->id,
                'route_sheet_id' => $sheet->id,
            ];
        }

        return response()->json(['alerts' => $alerts, 'count' => count($alerts)]);
    }
}
