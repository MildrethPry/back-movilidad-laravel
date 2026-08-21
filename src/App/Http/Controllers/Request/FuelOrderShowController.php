<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Requests\Models\FuelOrder;
use Illuminate\Http\Request;

class FuelOrderShowController extends Controller
{
    public function __invoke(Request $request, $codigo_orden)
    {
        $fuelOrder = FuelOrder::with([
            'routeSheet.vehicle',
            'routeSheet.driver.user',
            'routeSheet.request.requester',
            'station',
        ])->where('order_code', $codigo_orden)->first();

        if (! $fuelOrder) {
            return response()->json([
                'message' => 'El código de vale de combustible ingresado no existe.',
            ], 404);
        }

        return response()->json($fuelOrder);
    }
}
