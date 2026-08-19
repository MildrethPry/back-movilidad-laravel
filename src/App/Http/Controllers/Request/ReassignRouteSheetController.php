<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Domain\Auth\Models\Driver;
use Domain\Requests\Models\RouteSheet;
use Domain\Requests\Support\RequestWorkflow;
use Domain\Vehicles\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReassignRouteSheetController extends Controller
{
    public function __invoke(Request $request, int $id)
    {
        $user = $request->user();
        if (! $user->hasRole(['secretaria', 'jefe_transporte'])) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $request->validate([
            'driver_id' => 'required|integer',
            'vehicle_id' => 'nullable|integer',
        ]);

        $sheet = RouteSheet::with(['driver', 'vehicle', 'request'])->findOrFail($id);

        if (! in_array($sheet->driver_response, ['rechazado', 'pendiente'], true)) {
            return response()->json(['message' => 'Solo se reasignan viajes pendientes o rechazados.'], 400);
        }

        return DB::transaction(function () use ($request, $sheet, $user) {
            $newDriver = Driver::findOrFail((int) $request->input('driver_id'));
            if (! $newDriver->is_available) {
                throw ValidationException::withMessages([
                    'driver_id' => ['El conductor no está disponible.'],
                ]);
            }

            $license = $newDriver->licenses()
                ->where('current_points', '>', 0)
                ->where('expiration_date', '>=', Carbon::today())
                ->first();

            if (! $license) {
                throw ValidationException::withMessages([
                    'driver_id' => ['Licencia no vigente.'],
                ]);
            }

            $vehicleId = (int) ($request->input('vehicle_id') ?: $sheet->vehicle_id);
            $vehicle = Vehicle::findOrFail($vehicleId);
            if ($vehicle->operational_status !== 'disponible' && $vehicle->id !== $sheet->vehicle_id) {
                throw ValidationException::withMessages([
                    'vehicle_id' => ['Vehículo no disponible.'],
                ]);
            }

            if ($sheet->driver && $sheet->driver_response === 'aceptado') {
                $sheet->driver->update(['is_available' => true]);
            }

            if ($sheet->vehicle && $sheet->vehicle_id !== $vehicle->id) {
                $sheet->vehicle->update(['operational_status' => 'disponible']);
            }

            $sheet->update([
                'driver_id' => $newDriver->id,
                'vehicle_id' => $vehicle->id,
                'driver_response' => 'pendiente',
                'driver_reject_reason' => null,
                'driver_responded_at' => null,
                'trip_status' => 'programado',
            ]);

            RequestWorkflow::record(
                $sheet->request,
                $sheet->request->status,
                'REASIGNACION',
                $user->id,
                'Se reasignó conductor/vehículo.'
            );

            return response()->json([
                'message' => 'Reasignación realizada.',
                'route_sheet' => $sheet->fresh(['driver.user', 'vehicle', 'request']),
            ]);
        });
    }
}
