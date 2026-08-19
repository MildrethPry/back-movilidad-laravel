<?php

namespace Domain\Requests\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'route_sheet_id',
    'sequence',
    'location',
    'visited_canton',
    'departure_at',
    'arrival_time',
    'odometer_km',
    'latitude',
    'longitude',
    'notes',
])]
class RouteSheetStop extends Model
{
    protected $table = 'route_sheet_stops';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'departure_at' => 'datetime',
            'arrival_time' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function routeSheet(): BelongsTo
    {
        return $this->belongsTo(RouteSheet::class, 'route_sheet_id');
    }
}
