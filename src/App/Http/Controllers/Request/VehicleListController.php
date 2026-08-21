<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Vehicles\Models\Vehicle;
use Illuminate\Http\Request;

class VehicleListController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole(['secretaria', 'jefe_transporte'])) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $vehicles = Vehicle::all()->map(function ($vehicle) {
            $oilChangeRequired = $vehicle->current_mileage >= $vehicle->next_oil_change_mileage;

            $statusLabel = 'available';
            $statusDetails = 'Disponible';

            if ($vehicle->operational_status === 'en_viaje') {
                $statusLabel = 'on_trip';
                $statusDetails = 'En viaje / asignación activa';
            } elseif ($vehicle->operational_status === 'en_taller') {
                $statusLabel = 'in_maintenance';
                $statusDetails = 'En taller / mantenimiento';
            } elseif ($vehicle->operational_status === 'inactivo') {
                $statusLabel = 'inactive';
                $statusDetails = 'Inactivo';
            } elseif ($oilChangeRequired) {
                $statusLabel = 'blocked_oil';
                $statusDetails = 'Bloqueado: requiere cambio de aceite';
            }

            return [
                'id' => $vehicle->id,
                'plate' => $vehicle->plate,
                'brand' => $vehicle->brand,
                'model' => $vehicle->model,
                'year' => $vehicle->year,
                'color' => $vehicle->color,
                'fuel_type' => $vehicle->fuel_type,
                'current_mileage' => $vehicle->current_mileage,
                'next_oil_change_mileage' => $vehicle->next_oil_change_mileage,
                'operational_status' => $vehicle->operational_status,
                'status_label' => $statusLabel,
                'status_details' => $statusDetails,
                'is_selectable' => ($vehicle->operational_status === 'disponible' && ! $oilChangeRequired),
            ];
        });

        return response()->json($vehicles);
    }
}
