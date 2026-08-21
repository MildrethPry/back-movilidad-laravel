<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Requests\Models\ChecklistInventoryComponent;
use Illuminate\Http\Request;

class ChecklistComponentsController extends Controller
{
    public function __invoke(Request $request)
    {
        $components = ChecklistInventoryComponent::all();

        return response()->json($components);
    }
}
