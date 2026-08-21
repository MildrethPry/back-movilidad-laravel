<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Models\RouteSheet;
use Domain\Vehicles\Models\Vehicle;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function solicitudes(Request $request)
    {
        $user = $request->user();
        if (! $user->hasRole(['secretaria', 'jefe_transporte', 'vicerrector', 'rector', 'responsable_facultad', 'docente', 'solicitante'])) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $query = MobilizationRequest::with(['requester', 'secretariaApprover', 'rectorateApprover', 'routeSheet.vehicle', 'routeSheet.driver.user']);

        if ($user->hasRole(['docente', 'solicitante']) && ! $user->hasRole(['secretaria', 'responsable_facultad', 'vicerrector'])) {
            $query->where('requester_id', $user->id);
        } elseif ($user->hasRole(['responsable_facultad']) && ! $user->hasRole(['secretaria'])) {
            $query->whereHas('requester', fn ($q) => $q->where('faculty_institution', $user->faculty_institution));
        } elseif ($user->hasRole(['vicerrector', 'rector']) && ! $user->hasRole(['secretaria'])) {
            $query->where('mobilization_type', 'externa');
        }

        if ($request->filled('from')) {
            $query->whereDate('departure_date', '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('departure_date', '<=', $request->query('to'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('type')) {
            $query->where('mobilization_type', $request->query('type'));
        }

        $rows = $query->orderByDesc('id')->get()->map(function (MobilizationRequest $r) {
            return [
                'id' => $r->id,
                'tipo' => $r->mobilization_type,
                'origen' => $r->origin,
                'destino' => $r->destination,
                'salida' => optional($r->departure_date)?->toDateString(),
                'retorno' => optional($r->return_date)?->toDateString(),
                'estado' => $r->status,
                'solicitante' => trim(($r->requester?->first_name.' '.$r->requester?->last_name) ?: ''),
                'facultad' => $r->requester?->faculty_institution,
                'costo_proyectado' => $r->projected_cost,
                'vehiculo' => $r->routeSheet?->vehicle?->plate,
                'conductor' => trim(($r->routeSheet?->driver?->user?->first_name.' '.$r->routeSheet?->driver?->user?->last_name) ?: ''),
                'estado_viaje' => $r->routeSheet?->trip_status,
            ];
        });

        if ($request->query('format') === 'csv') {
            return $this->csv('reporte_solicitudes.csv', [
                'id', 'tipo', 'origen', 'destino', 'salida', 'retorno', 'estado',
                'solicitante', 'facultad', 'costo_proyectado', 'vehiculo', 'conductor', 'estado_viaje',
            ], $rows->all());
        }

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'total' => $rows->count(),
            'rows' => $rows,
            'summary' => [
                'por_estado' => $rows->groupBy('estado')->map->count(),
                'por_tipo' => $rows->groupBy('tipo')->map->count(),
                'costo_total' => $rows->sum('costo_proyectado'),
            ],
        ]);
    }

    public function viajes(Request $request)
    {
        $user = $request->user();
        if (! $user->hasRole(['secretaria', 'jefe_transporte', 'vicerrector', 'rector'])) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $query = RouteSheet::with(['request.requester', 'vehicle', 'driver.user', 'stops']);

        if ($request->filled('from')) {
            $query->whereHas('request', fn ($q) => $q->whereDate('departure_date', '>=', $request->query('from')));
        }
        if ($request->filled('to')) {
            $query->whereHas('request', fn ($q) => $q->whereDate('departure_date', '<=', $request->query('to')));
        }

        $rows = $query->orderByDesc('id')->get()->map(function (RouteSheet $s) {
            return [
                'hoja_ruta_id' => $s->id,
                'solicitud' => $s->request?->destination,
                'salida' => optional($s->request?->departure_date)?->toDateString(),
                'conductor' => trim(($s->driver?->user?->first_name.' '.$s->driver?->user?->last_name) ?: ''),
                'vehiculo' => $s->vehicle?->plate,
                'respuesta_conductor' => $s->driver_response,
                'estado_viaje' => $s->trip_status,
                'paradas' => $s->stops->count(),
                'km_inicial' => $s->initial_mileage,
                'km_final' => $s->final_mileage,
            ];
        });

        if ($request->query('format') === 'csv') {
            return $this->csv('reporte_viajes.csv', [
                'hoja_ruta_id', 'destino', 'salida', 'conductor', 'vehiculo',
                'respuesta_conductor', 'estado_viaje', 'paradas', 'km_inicial', 'km_final',
            ], $rows->all());
        }

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'total' => $rows->count(),
            'rows' => $rows,
        ]);
    }

    public function flota(Request $request)
    {
        $user = $request->user();
        if (! $user->hasRole(['secretaria', 'jefe_transporte'])) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $rows = Vehicle::all()->map(function (Vehicle $v) {
            return [
                'placa' => $v->plate,
                'unidad' => trim($v->brand.' '.$v->model),
                'km_actual' => $v->current_mileage,
                'proximo_aceite' => $v->next_oil_change_mileage,
                'estado' => $v->operational_status,
                'mantenimiento_vencido' => $v->current_mileage >= $v->next_oil_change_mileage ? 'si' : 'no',
            ];
        });

        if ($request->query('format') === 'csv') {
            return $this->csv('reporte_flota.csv', [
                'placa', 'unidad', 'km_actual', 'proximo_aceite', 'estado', 'mantenimiento_vencido',
            ], $rows->all());
        }

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'total' => $rows->count(),
            'rows' => $rows,
            'summary' => [
                'por_estado' => $rows->groupBy('estado')->map->count(),
                'mantenimiento_vencido' => $rows->where('mantenimiento_vencido', 'si')->count(),
            ],
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, mixed>>  $rows
     */
    private function csv(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, $headers, ';');
            foreach ($rows as $row) {
                $line = [];
                foreach ($headers as $h) {
                    $line[] = $row[$h] ?? '';
                }
                fputcsv($out, $line, ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
