<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Alerts\Actions\GenerateAlertsAction;
use Domain\Alerts\Models\Alert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AlertsController extends Controller
{
    public function index(Request $request, GenerateAlertsAction $generate): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole(['secretaria', 'jefe_transporte', 'conductor', 'chofer', 'mecanico'])) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $generate->execute();

        $alerts = Alert::query()
            ->where('created_at', '>=', now()->subDays(GenerateAlertsAction::ALERT_LIFETIME_DAYS))
            ->orderByRaw("case severity when 'alta' then 0 when 'media' then 1 else 2 end")
            ->orderByDesc('created_at')
            ->get();

        $readIds = $alerts->isEmpty()
            ? []
            : DB::table('alert_reads')
                ->where('user_id', $user->id)
                ->whereIn('alert_id', $alerts->pluck('id'))
                ->pluck('alert_id')
                ->all();

        $readSet = array_flip(array_map('intval', $readIds));

        $payload = $alerts->map(fn (Alert $alert) => [
            'id' => $alert->id,
            'type' => $alert->type,
            'severity' => $alert->severity,
            'title' => $alert->title,
            'message' => $alert->message,
            'route' => $alert->route,
            'created_at' => $alert->created_at?->toIso8601String(),
            'read' => isset($readSet[$alert->id]),
        ])->values();

        $unread = $payload->where('read', false);
        $importantUnread = $unread->where('severity', 'alta');

        return response()->json([
            'alerts' => $payload,
            'unread_count' => $unread->count(),
            'important_unread_count' => $importantUnread->count(),
        ]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user->hasRole(['secretaria', 'jefe_transporte', 'conductor', 'chofer', 'mecanico'])) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $alert = Alert::find($id);

        if (! $alert) {
            return response()->json(['message' => 'Alerta no encontrada.'], 404);
        }

        $alert->readers()->syncWithoutDetaching([$user->id => ['read_at' => now()]]);

        return response()->json(['message' => 'Alerta marcada como leída.']);
    }
}
