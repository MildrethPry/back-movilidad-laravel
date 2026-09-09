<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Requests\Models\RateConfiguration;
use Illuminate\Http\Request;

class RateConfigurationListController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->hasRole(['secretaria', 'jefe_transporte'])) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $rates = RateConfiguration::orderBy('rate_group')->orderBy('rate_key')->get();

        return response()->json($rates);
    }
}
