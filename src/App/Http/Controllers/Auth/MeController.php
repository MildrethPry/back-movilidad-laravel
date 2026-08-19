<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role_id && $user->roles()->count() === 0) {
            $user->roles()->syncWithoutDetaching([$user->role_id]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $user->toAuthArray(),
        ]);
    }
}
