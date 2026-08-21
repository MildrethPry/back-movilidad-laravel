<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Domain\Requests\Models\RouteSheet;
use Illuminate\Http\Request;
use Throwable;

class AgendaController extends Controller
{
    /**
     * Controlador invocable: agenda semanal paginada de viajes por conductor/vehículo.
     */
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (! $user->hasRole(['secretaria', 'jefe_transporte'])) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $from = $this->parseDate($request->query('from'), now()->startOfWeek());
        $to = $this->parseDate($request->query('to'), now()->endOfWeek());

        if ($from === null || $to === null) {
            return response()->json(['message' => 'Rango de fechas inválido.'], 422);
        }

        if ($from->gt($to)) {
            return response()->json([
                'message' => 'La fecha "desde" no puede ser posterior a la fecha "hasta".',
            ], 422);
        }

        $query = RouteSheet::query()
            ->join('mobilization_requests as mr', 'mr.id', '=', 'route_sheets.request_id')
            ->select('route_sheets.*')
            ->with([
                'request:id,destination,departure_date,return_date',
                'vehicle:id,plate',
                'driver.user:id,first_name,last_name',
            ])
            ->whereBetween('mr.departure_date', [$from->toDateString(), $to->toDateString()]);

        if ($request->filled('trip_status')) {
            $query->where('route_sheets.trip_status', $request->query('trip_status'));
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->query('q'));
            if ($q !== '') {
                $query->where(function ($sub) use ($q) {
                    $sub->where('mr.destination', 'like', "%{$q}%")
                        ->orWhereHas('vehicle', fn ($v) => $v->where('plate', 'like', "%{$q}%"))
                        ->orWhereHas('driver.user', fn ($u) => $u
                            ->where('first_name', 'like', "%{$q}%")
                            ->orWhere('last_name', 'like', "%{$q}%"));
                });
            }
        }

        $events = $query
            ->orderBy('mr.departure_date')
            ->orderBy('route_sheets.id')
            ->paginate($this->resolvePerPage($request))
            ->withQueryString()
            ->through(fn (RouteSheet $sheet) => $this->map($sheet));

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'events' => $events,
        ]);
    }

    private function parseDate(?string $value, Carbon $default): ?Carbon
    {
        if ($value === null || $value === '') {
            return $default->copy()->startOfDay();
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    private function map(RouteSheet $sheet): array
    {
        return [
            'id' => $sheet->id,
            'date' => $sheet->request?->departure_date?->toDateString(),
            'return_date' => $sheet->request?->return_date?->toDateString(),
            'destination' => $sheet->request?->destination,
            'trip_status' => $sheet->trip_status,
            'driver_response' => $sheet->driver_response,
            'driver' => trim(
                ($sheet->driver?->user?->first_name ?? '').' '.($sheet->driver?->user?->last_name ?? '')
            ),
            'vehicle' => $sheet->vehicle?->plate,
            'vehicle_id' => $sheet->vehicle_id,
            'driver_id' => $sheet->driver_id,
        ];
    }
}
