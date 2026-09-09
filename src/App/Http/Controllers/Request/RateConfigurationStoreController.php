<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Auth\Models\SystemLog;
use Domain\Requests\Models\RateConfiguration;
use Illuminate\Http\Request;

class RateConfigurationStoreController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole(['secretaria', 'jefe_transporte'])) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $data = $request->validate([
            'rate_key' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', 'unique:rate_configurations,rate_key'],
            'rate_label' => 'required|string|max:120',
            'rate_group' => 'required|in:allowance,fuel,other',
            'rate_value' => 'required|numeric|min:0.01',
        ], [
            'rate_key.regex' => 'Use únicamente minúsculas, números y guiones bajos en la clave.',
            'rate_key.unique' => 'Ya existe una tarifa con esa clave.',
            'rate_value.min' => 'El valor debe ser mayor que cero.',
        ]);

        $rate = RateConfiguration::create($data);

        SystemLog::create([
            'user_id' => $user->id,
            'action' => "Creó tarifa {$rate->rate_key}",
            'affected_table' => 'rate_configurations',
            'record_id' => $rate->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => "Tarifa '{$rate->rate_label}' creada con éxito.",
            'rate' => $rate,
        ], 201);
    }
}
