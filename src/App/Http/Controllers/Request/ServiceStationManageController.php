<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Requests\Models\ServiceStation;
use Illuminate\Http\Request;

class ServiceStationManageController extends Controller
{
    public function store(Request $request)
    {
        if (! $request->user()->hasRole(['secretaria', 'jefe_transporte'])) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $data = $request->validate([
            'commercial_name' => 'required|string|max:150',
            'ruc' => 'required|string|max:13|unique:service_stations,ruc',
            'address' => 'required|string|max:255',
            'price_per_liter' => 'nullable|numeric|min:0',
            'monthly_quota_liters' => 'nullable|numeric|min:0',
            'contract_start' => 'nullable|date',
            'contract_end' => 'nullable|date',
            'active_agreement' => 'nullable|boolean',
        ]);

        $station = ServiceStation::create([
            ...$data,
            'active_agreement' => $data['active_agreement'] ?? true,
            'consumed_liters' => 0,
        ]);

        return response()->json(['message' => 'Contrato de gasolinera registrado.', 'station' => $station], 201);
    }

    public function update(Request $request, int $id)
    {
        if (! $request->user()->hasRole(['secretaria', 'jefe_transporte'])) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $station = ServiceStation::findOrFail($id);
        $data = $request->validate([
            'commercial_name' => 'sometimes|string|max:150',
            'address' => 'sometimes|string|max:255',
            'price_per_liter' => 'nullable|numeric|min:0',
            'monthly_quota_liters' => 'nullable|numeric|min:0',
            'consumed_liters' => 'nullable|numeric|min:0',
            'contract_start' => 'nullable|date',
            'contract_end' => 'nullable|date',
            'active_agreement' => 'nullable|boolean',
        ]);

        $station->update($data);

        return response()->json(['message' => 'Gasolinera actualizada.', 'station' => $station]);
    }
}
