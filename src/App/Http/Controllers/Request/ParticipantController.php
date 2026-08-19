<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Auth\Models\User;
use Domain\Auth\Support\RoleCatalog;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Models\PassengerManifest;
use Illuminate\Http\Request;

class ParticipantController extends Controller
{
    public function index(Request $request, int $id)
    {
        $user = $request->user();
        $mobilization = MobilizationRequest::findOrFail($id);

        $mobilization->loadMissing('requester');

        $allowed = $user->hasRole(['secretaria', 'jefe_transporte', 'vicerrector', 'rector'])
            || $mobilization->requester_id === $user->id
            || (
                $user->hasRole(['responsable_facultad'])
                && optional($mobilization->requester)->faculty_institution === $user->faculty_institution
            );

        if (! $allowed) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $participants = PassengerManifest::with('user')
            ->where('request_id', $id)
            ->get();

        return response()->json([
            'participants' => $participants,
            'summary' => [
                'total' => $participants->count(),
                'aceptados' => $participants->where('invitation_status', 'aceptado')->count(),
                'rechazados' => $participants->where('invitation_status', 'rechazado')->count(),
                'pendientes' => $participants->where('invitation_status', 'invitado')->count(),
            ],
        ]);
    }

    public function store(Request $request, int $id)
    {
        $user = $request->user();
        $mobilization = MobilizationRequest::findOrFail($id);

        if ($mobilization->requester_id !== $user->id && ! $user->hasRole(['secretaria', 'jefe_transporte'])) {
            return response()->json(['message' => 'Solo el docente solicitante puede invitar.'], 403);
        }

        $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'email' => 'nullable|email',
            'national_id' => 'nullable|string|max:10',
        ]);

        $participant = null;
        if ($request->filled('user_id')) {
            $participant = User::find($request->integer('user_id'));
        } elseif ($request->filled('email')) {
            $participant = User::where('email', $request->input('email'))->first();
        } elseif ($request->filled('national_id')) {
            $participant = User::where('national_id', $request->input('national_id'))->first();
        }

        if (! $participant) {
            return response()->json(['message' => 'Estudiante no encontrado en el directorio.'], 404);
        }

        if (! $participant->hasRole([RoleCatalog::ESTUDIANTE, 'pasajero'])) {
            return response()->json(['message' => 'El usuario no tiene rol de estudiante.'], 422);
        }

        $row = PassengerManifest::firstOrCreate(
            ['request_id' => $id, 'user_id' => $participant->id],
            [
                'attended' => false,
                'invitation_status' => 'invitado',
            ]
        );

        return response()->json([
            'message' => 'Participante invitado.',
            'participant' => $row->load('user'),
        ], 201);
    }

    public function myInvitations(Request $request)
    {
        $rows = PassengerManifest::with(['request.requester', 'request.routeSheet.vehicle', 'request.routeSheet.driver.user'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        return response()->json($rows);
    }

    public function respond(Request $request, int $id)
    {
        $row = PassengerManifest::with('request')->findOrFail($id);
        if ($row->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        if ($row->invitation_status !== 'invitado') {
            return response()->json(['message' => 'Ya respondió esta invitación.'], 400);
        }

        $action = $request->input('action', 'accept');
        if ($action === 'reject') {
            $request->validate(['reason' => 'required|string|max:500']);
            $row->update([
                'invitation_status' => 'rechazado',
                'reject_reason' => $request->input('reason'),
                'responded_at' => now(),
                'attended' => false,
            ]);
        } else {
            $row->update([
                'invitation_status' => 'aceptado',
                'responded_at' => now(),
                'attended' => true,
                'reject_reason' => null,
            ]);
        }

        return response()->json([
            'message' => 'Respuesta registrada.',
            'participant' => $row->fresh('request'),
        ]);
    }

    public function searchStudents(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $students = User::query()
            ->where(function ($query) {
                $query->whereHas('roles', fn ($r) => $r->whereIn('name', ['estudiante', 'pasajero']))
                    ->orWhereHas('role', fn ($r) => $r->whereIn('name', ['estudiante', 'pasajero']));
            })
            ->where(function ($query) use ($q) {
                $query->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('national_id', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            })
            ->limit(20)
            ->get(['id', 'first_name', 'last_name', 'email', 'national_id', 'faculty_institution']);

        return response()->json($students);
    }
}
