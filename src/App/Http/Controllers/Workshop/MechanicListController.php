<?php

namespace App\Http\Controllers\Workshop;

use App\Http\Controllers\Controller;
use Domain\Auth\Models\User;
use Domain\Auth\Support\RoleCatalog;

class MechanicListController extends Controller
{
    public function __invoke()
    {
        $mechanics = User::query()
            ->where(function ($query) {
                $query->whereHas('roles', fn ($q) => $q->where('name', RoleCatalog::MECANICO))
                    ->orWhereHas('role', fn ($q) => $q->where('name', RoleCatalog::MECANICO));
            })
            ->with(['role', 'roles'])
            ->get();

        return response()->json($mechanics);
    }
}
