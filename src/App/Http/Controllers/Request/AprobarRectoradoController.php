<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Support\RequestWorkflow;
use Illuminate\Http\Request;

class AprobarRectoradoController extends Controller
{
    public function __invoke(Request $request, $id)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole(['vicerrector', 'rector'])) {
            return response()->json(['message' => 'Acceso denegado: se requiere Vicerrector.'], 403);
        }

        $mobilizationRequest = MobilizationRequest::findOrFail($id);

        if ($mobilizationRequest->status !== 'pendiente_rectorado') {
            return response()->json(['message' => 'La solicitud no está pendiente de Vicerrectorado.'], 400);
        }

        $action = $request->input('action', 'approve');

        if ($action === 'reject') {
            $request->validate([
                'justification' => 'required|string|max:500',
            ]);

            $from = $mobilizationRequest->status;
            $mobilizationRequest->update([
                'status' => 'rechazada',
                'rectorate_approver_id' => $user->id,
                'travel_reason' => $mobilizationRequest->travel_reason."\n\n[RECHAZADO VICERRECTORADO: ".$request->input('justification').']',
            ]);

            RequestWorkflow::record(
                $mobilizationRequest,
                'rechazada',
                'VICERRECTOR_RECHAZA',
                $user->id,
                $request->input('justification'),
                $from
            );

            return response()->json([
                'message' => 'Solicitud rechazada.',
                'request' => $mobilizationRequest,
            ]);
        }

        $from = $mobilizationRequest->status;
        $mobilizationRequest->update([
            'status' => 'aprobado_rectorado',
            'rectorate_approver_id' => $user->id,
        ]);

        RequestWorkflow::record(
            $mobilizationRequest,
            'aprobado_rectorado',
            'VICERRECTOR_APRUEBA',
            $user->id,
            'Aprobado por Vicerrectorado.',
            $from
        );

        return response()->json([
            'message' => 'Solicitud aprobada por Vicerrectorado.',
            'request' => $mobilizationRequest,
        ]);
    }
}
