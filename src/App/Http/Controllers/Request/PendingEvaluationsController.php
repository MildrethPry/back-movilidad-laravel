<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Requests\Models\RouteSheet;
use Illuminate\Http\Request;

class PendingEvaluationsController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $pending = RouteSheet::with(['vehicle', 'driver.user', 'request'])
            ->where('trip_status', 'pendiente_feedback')
            ->where(function ($query) use ($user) {
                $query
                    ->whereHas('request', function ($requestQuery) use ($user) {
                        $requestQuery->where('requester_id', $user->id);
                    })
                    ->orWhereHas('request.passengers', function ($passengerQuery) use ($user) {
                        $passengerQuery->where('user_id', $user->id);
                    });
            })
            ->whereDoesntHave('evaluations', function ($query) use ($user) {
                $query->where('passenger_id', $user->id);
            })
            ->get();

        return response()->json($pending);
    }
}
