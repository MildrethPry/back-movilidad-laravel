<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Auth\Models\Driver;
use Domain\Requests\Models\RouteSheet;
use Illuminate\Http\Request;

class MyVehicleController extends Controller
{
    public function __invoke(Request $request)
    {
        $driver = Driver::where('user_id', $request->user()->id)->first();
        if (! $driver) {
            return response()->json(['message' => 'No es conductor.'], 403);
        }

        $active = RouteSheet::with('vehicle')
            ->where('driver_id', $driver->id)
            ->whereIn('trip_status', ['programado', 'en_ruta'])
            ->where('driver_response', 'aceptado')
            ->latest('id')
            ->first();

        if (! $active || ! $active->vehicle) {
            return response()->json(['vehicle' => null, 'message' => 'Sin vehículo asignado actualmente.']);
        }

        $vehicle = $active->vehicle;

        return response()->json([
            'vehicle' => $vehicle,
            'route_sheet_id' => $active->id,
            'km_to_maintenance' => max(0, $vehicle->next_oil_change_mileage - $vehicle->current_mileage),
            'maintenance_due' => $vehicle->current_mileage >= $vehicle->next_oil_change_mileage,
        ]);
    }
}
