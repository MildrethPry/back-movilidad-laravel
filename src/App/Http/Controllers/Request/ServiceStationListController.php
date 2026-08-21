<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Requests\Models\ServiceStation;
use Illuminate\Http\Request;

class ServiceStationListController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $stations = ServiceStation::when(
            ! $user->hasRole(['secretaria', 'jefe_transporte']),
            fn ($query) => $query->where('active_agreement', true)
        )
            ->orderBy('commercial_name')
            ->get();

        return response()->json($stations);
    }
}
