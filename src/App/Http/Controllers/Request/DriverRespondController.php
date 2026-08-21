<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Auth\Models\Driver;
use Domain\Requests\Models\RouteSheet;
use Domain\Requests\Support\RequestWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverRespondController extends Controller
{
    public function __invoke(Request $request, int $id)
    {
        $user = $request->user();
        $driver = Driver::where('user_id', $user->id)->first();
        if (! $driver) {
            return response()->json(['message' => 'No es conductor.'], 403);
        }

        $action = $request->input('action', 'accept');
        if ($action === 'reject') {
            $request->validate(['reason' => 'required|string|max:500']);
        }

        $sheet = RouteSheet::with(['request', 'vehicle', 'driver'])->findOrFail($id);
        if ($sheet->driver_id !== $driver->id) {
            return response()->json(['message' => 'Esta asignación no le corresponde.'], 403);
        }

        if ($sheet->driver_response !== 'pendiente') {
            return response()->json(['message' => 'Ya respondió esta asignación.'], 400);
        }

        return DB::transaction(function () use ($request, $sheet, $user, $action, $driver) {
            if ($action === 'reject') {
                $sheet->update([
                    'driver_response' => 'rechazado',
                    'driver_reject_reason' => $request->input('reason'),
                    'driver_responded_at' => now(),
                ]);

                RequestWorkflow::record(
                    $sheet->request,
                    $sheet->request->status,
                    'CONDUCTOR_RECHAZA',
                    $user->id,
                    $request->input('reason')
                );

                return response()->json([
                    'message' => 'Asignación rechazada. Secretaría debe reasignar.',
                    'route_sheet' => $sheet->fresh(['request', 'vehicle', 'driver.user']),
                ]);
            }

            $sheet->update([
                'driver_response' => 'aceptado',
                'driver_responded_at' => now(),
                'driver_reject_reason' => null,
            ]);

            $driver->update(['is_available' => false]);
            $sheet->vehicle->update(['operational_status' => 'en_viaje']);

            RequestWorkflow::record(
                $sheet->request,
                $sheet->request->status,
                'CONDUCTOR_ACEPTA',
                $user->id,
                'Conductor aceptó la asignación.'
            );

            return response()->json([
                'message' => 'Asignación aceptada.',
                'route_sheet' => $sheet->fresh(['request', 'vehicle', 'driver.user']),
            ]);
        });
    }
}
