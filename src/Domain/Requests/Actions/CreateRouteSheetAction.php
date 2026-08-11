<?php

namespace Domain\Requests\Actions;

use Carbon\Carbon;
use Domain\Auth\Models\Driver;
use Domain\Auth\Models\SystemLog;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Models\RouteSheet;
use Domain\Requests\Support\RequestWorkflow;
use Domain\Vehicles\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateRouteSheetAction
{
    public function execute(int $requestId, int $vehicleId, int $driverId, int $transportChiefId, string $ipAddress = '127.0.0.1'): RouteSheet
    {
        return DB::transaction(function () use ($requestId, $vehicleId, $driverId, $transportChiefId, $ipAddress) {
            $request = MobilizationRequest::findOrFail($requestId);

            $assignable = $request->mobilization_type === 'interna'
                ? in_array($request->status, ['autorizada_secretaria', 'pendiente'], true)
                : $request->status === 'aprobado_rectorado';

            if (! $assignable) {
                throw ValidationException::withMessages([
                    'request_id' => ['La solicitud no cuenta con las aprobaciones jerárquicas requeridas.'],
                ]);
            }

            if ($request->routeSheet) {
                throw ValidationException::withMessages([
                    'request_id' => ['La solicitud ya tiene una hoja de ruta asignada.'],
                ]);
            }

            $vehicle = Vehicle::findOrFail($vehicleId);

            if ($vehicle->current_mileage >= $vehicle->next_oil_change_mileage) {
                throw ValidationException::withMessages([
                    'vehicle_id' => ['Unidad bloqueada: requiere cambio de aceite preventivo.'],
                ]);
            }

            if ($vehicle->operational_status !== 'disponible') {
                throw ValidationException::withMessages([
                    'vehicle_id' => ['El vehículo no está disponible.'],
                ]);
            }

            $driver = Driver::findOrFail($driverId);

            if (! $driver->is_available) {
                throw ValidationException::withMessages([
                    'driver_id' => ['El conductor no está disponible.'],
                ]);
            }

            $activeLicense = $driver->licenses()
                ->where('current_points', '>', 0)
                ->where('expiration_date', '>=', Carbon::today())
                ->first();

            if (! $activeLicense) {
                throw ValidationException::withMessages([
                    'driver_id' => ['El conductor no tiene licencia vigente con puntos.'],
                ]);
            }

            $from = $request->status;
            $request->update(['status' => 'aprobada']);

            $routeSheet = RouteSheet::create([
                'request_id' => $requestId,
                'vehicle_id' => $vehicleId,
                'driver_id' => $driverId,
                'transport_chief_id' => $transportChiefId,
                'initial_mileage' => $vehicle->current_mileage,
                'trip_status' => 'programado',
                'driver_response' => 'pendiente',
            ]);

            RequestWorkflow::record(
                $request,
                'aprobada',
                'ASIGNACION_RECURSOS',
                $transportChiefId,
                'Se asignó conductor y vehículo. Pendiente aceptación del conductor.',
                $from
            );

            SystemLog::create([
                'user_id' => $transportChiefId,
                'action' => 'ROUTE_SHEET_CREATED',
                'affected_table' => 'route_sheets',
                'record_id' => $routeSheet->id,
                'ip_address' => $ipAddress,
            ]);

            return $routeSheet->load(['vehicle', 'driver.user', 'request']);
        });
    }
}
