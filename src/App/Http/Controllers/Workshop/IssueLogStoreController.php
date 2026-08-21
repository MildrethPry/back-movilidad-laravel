<?php

namespace App\Http\Controllers\Workshop;

use App\Http\Controllers\Controller;
use Domain\Vehicles\Models\Vehicle;
use Domain\Workshop\Models\IssueLog;
use Illuminate\Http\Request;

class IssueLogStoreController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'vehicle_id' => 'required|integer|exists:vehicles,id',
            'route_sheet_id' => 'nullable|integer|exists:route_sheets,id',
            'description' => 'required|string|max:1000',
            'city' => 'nullable|string|max:100',
            'issue_type' => 'nullable|string|max:50',
            'breakdown_date' => 'nullable|date',
        ]);

        Vehicle::findOrFail($data['vehicle_id']);

        $description = trim(
            ($data['issue_type'] ?? 'Novedad')
            .($data['city'] ? ' en '.$data['city'] : '')
            .': '
            .$data['description']
        );

        $issue = IssueLog::create([
            'vehicle_id' => $data['vehicle_id'],
            'route_sheet_id' => $data['route_sheet_id'] ?? null,
            'reporting_driver_id' => $user->id,
            'breakdown_date' => $data['breakdown_date'] ?? now()->toDateString(),
            'description' => $description,
            'status' => 'pendiente',
        ]);

        return response()->json(['message' => 'Novedad registrada.', 'issue' => $issue], 201);
    }
}
