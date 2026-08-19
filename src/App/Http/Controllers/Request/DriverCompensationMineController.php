<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Auth\Models\Driver;
use Domain\Requests\Models\DriverCompensation;
use Illuminate\Http\Request;

class DriverCompensationMineController extends Controller
{
    public function index(Request $request)
    {
        $driver = Driver::where('user_id', $request->user()->id)->first();
        if (! $driver) {
            return response()->json([]);
        }

        $rows = DriverCompensation::with('routeSheet.request')
            ->whereHas('routeSheet', fn ($q) => $q->where('driver_id', $driver->id))
            ->orderByDesc('id')
            ->get();

        return response()->json($rows);
    }

    public function confirm(Request $request, int $id)
    {
        $driver = Driver::where('user_id', $request->user()->id)->first();
        if (! $driver) {
            return response()->json(['message' => 'No es conductor.'], 403);
        }

        $comp = DriverCompensation::with('routeSheet')->findOrFail($id);
        if ($comp->routeSheet?->driver_id !== $driver->id) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $action = $request->input('action', 'confirm');
        if ($action === 'dispute') {
            $request->validate(['observation' => 'required|string|max:500']);
            $comp->update([
                'payment_status' => 'en_disputa',
                'payment_receipt_url' => 'DISPUTA: '.$request->input('observation'),
            ]);
        } else {
            $comp->update([
                'payment_status' => 'confirmado_conductor',
            ]);
        }

        return response()->json([
            'message' => 'Respuesta de pago registrada.',
            'compensation' => $comp->fresh('routeSheet.request'),
        ]);
    }
}
