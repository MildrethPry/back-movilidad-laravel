<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Support\RequestWorkflow;
use Illuminate\Http\Request;

class AutorizarSecretariaController extends Controller
{
    public function __invoke(Request $request, int $id)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole(['secretaria', 'jefe_transporte'])) {
            return response()->json(['message' => 'Acceso denegado: se requiere Secretaría.'], 403);
        }

        $mobilization = MobilizationRequest::findOrFail($id);

        if ($mobilization->status !== 'pendiente_secretaria') {
            return response()->json(['message' => 'La solicitud no está pendiente de Secretaría.'], 400);
        }

        $action = $request->input('action', 'approve');
        $observation = $request->input('observation');

        if ($action === 'reject') {
            $request->validate([
                'observation' => 'required|string|max:500',
            ]);

            $from = $mobilization->status;
            $mobilization->update([
                'status' => 'rechazada',
                'secretaria_approver_id' => $user->id,
                'secretaria_observation' => $observation,
            ]);

            RequestWorkflow::record($mobilization, 'rechazada', 'SECRETARIA_RECHAZA', $user->id, $observation, $from);

            return response()->json([
                'message' => 'Solicitud rechazada por Secretaría.',
                'request' => $mobilization->fresh(['requester', 'secretariaApprover']),
            ]);
        }

        $from = $mobilization->status;
        $next = $mobilization->mobilization_type === 'externa'
            ? 'pendiente_rectorado'
            : 'autorizada_secretaria';

        $mobilization->update([
            'status' => $next,
            'secretaria_approver_id' => $user->id,
            'secretaria_observation' => $observation,
        ]);

        RequestWorkflow::record(
            $mobilization,
            $next,
            'SECRETARIA_AUTORIZA',
            $user->id,
            $observation ?? 'Autorizada por Secretaría.',
            $from
        );

        return response()->json([
            'message' => $next === 'pendiente_rectorado'
                ? 'Autorizada. Pasa a Vicerrectorado (viaje externo).'
                : 'Autorizada. Lista para asignación de recursos.',
            'request' => $mobilization->fresh(['requester', 'secretariaApprover']),
        ]);
    }
}
