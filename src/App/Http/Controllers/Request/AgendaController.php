<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Domain\Requests\Models\RouteSheet;
use Illuminate\Http\Request;

class AgendaController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (! $user->hasRole(['secretaria', 'jefe_transporte'])) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $from = Carbon::parse($request->query('from', now()->startOfWeek()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->endOfWeek()->toDateString()))->endOfDay();

        $trips = RouteSheet::with(['request', 'vehicle', 'driver.user'])
            ->whereHas('request', function ($q) use ($from, $to) {
                $q->whereBetween('departure_date', [$from->toDateString(), $to->toDateString()]);
            })
            ->get()
            ->map(function (RouteSheet $sheet) {
                return [
                    'id' => $sheet->id,
                    'date' => optional($sheet->request)->departure_date?->toDateString(),
                    'return_date' => optional($sheet->request)->return_date?->toDateString(),
                    'destination' => optional($sheet->request)->destination,
                    'trip_status' => $sheet->trip_status,
                    'driver_response' => $sheet->driver_response,
                    'driver' => optional($sheet->driver?->user)->first_name.' '.optional($sheet->driver?->user)->last_name,
                    'vehicle' => optional($sheet->vehicle)->plate,
                    'vehicle_id' => $sheet->vehicle_id,
                    'driver_id' => $sheet->driver_id,
                ];
            });

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'events' => $trips,
        ]);
    }
}
