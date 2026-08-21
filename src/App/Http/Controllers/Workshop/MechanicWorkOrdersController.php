<?php

namespace App\Http\Controllers\Workshop;

use App\Http\Controllers\Controller;
use Domain\Workshop\Models\WorkshopWorkOrder;
use Illuminate\Http\Request;

class MechanicWorkOrdersController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $query = WorkshopWorkOrder::with(['vehicle', 'issueLog', 'responsibleMechanic', 'supplyProvisions'])
            ->orderByDesc('id');

        if ($user->hasRole(['mecanico']) && ! $user->hasRole(['secretaria', 'jefe_transporte'])) {
            $query->where('responsible_mechanic_id', $user->id);
        }

        return response()->json($query->get());
    }
}
