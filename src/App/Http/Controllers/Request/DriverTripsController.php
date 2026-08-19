<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Auth\Models\Driver;
use Domain\Requests\Models\RouteSheet;
use Illuminate\Http\Request;

class DriverTripsController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        if ($user->hasRole(['secretaria', 'jefe_transporte'])) {
            $trips = RouteSheet::with(['request.requester', 'vehicle', 'driver.user'])
                ->orderByDesc('id')
                ->get();

            return response()->json($trips);
        }

        $driver = Driver::where('user_id', $user->id)->first();
        if (! $driver) {
            return response()->json([]);
        }

        $trips = RouteSheet::with(['request.requester', 'vehicle', 'driver.user', 'stops', 'compensation'])
            ->where('driver_id', $driver->id)
            ->orderByDesc('id')
            ->get();

        return response()->json($trips);
    }
}
