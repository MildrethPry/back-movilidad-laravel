<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    protected function resolvePerPage(
        Request $request,
        string $configKey = 'app.pagination_per_page',
        int $min = 1,
        int $max = 100
    ): int {
        $default = (int) config($configKey, 10);
        $perPage = (int) $request->integer('per_page', $default);

        return max($min, min($max, $perPage));
    }
}
