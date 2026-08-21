<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Auth\Models\Driver;
use Domain\Requests\Models\RouteSheet;
use Domain\Requests\Models\RouteSheetStop;
use Illuminate\Http\Request;

class RouteSheetStopController extends Controller
{
    public function index(Request $request, int $id)
    {
        $sheet = RouteSheet::with('stops')->findOrFail($id);
        $this->authorizeSheet($request, $sheet);

        return response()->json($sheet->stops);
    }

    public function store(Request $request, int $id)
    {
        $sheet = RouteSheet::findOrFail($id);
        $this->authorizeSheet($request, $sheet, true);

        $data = $request->validate([
            'location' => 'required|string|max:150',
            'departure_at' => 'nullable|date',
            'arrival_time' => 'nullable|date',
            'odometer_km' => 'nullable|integer|min:0',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|string|max:500',
        ]);

        $sequence = (int) RouteSheetStop::where('route_sheet_id', $id)->max('sequence') + 1;

        $stop = RouteSheetStop::create([
            'route_sheet_id' => $id,
            'sequence' => $sequence,
            'location' => $data['location'],
            'visited_canton' => $data['location'],
            'departure_at' => $data['departure_at'] ?? null,
            'arrival_time' => $data['arrival_time'] ?? now(),
            'odometer_km' => $data['odometer_km'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        if (! empty($data['odometer_km'])) {
            $sheet->update(['final_mileage' => $data['odometer_km']]);
        }

        return response()->json(['message' => 'Parada registrada.', 'stop' => $stop], 201);
    }

    private function authorizeSheet(Request $request, RouteSheet $sheet, bool $write = false): void
    {
        $user = $request->user();
        if ($user->hasRole(['secretaria', 'jefe_transporte'])) {
            return;
        }

        $driver = Driver::where('user_id', $user->id)->first();
        if ($driver && $sheet->driver_id === $driver->id) {
            return;
        }

        abort(response()->json(['message' => 'Acceso denegado.'], 403));
    }
}
