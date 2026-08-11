<?php

namespace Domain\Requests\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'commercial_name',
    'ruc',
    'address',
    'active_agreement',
    'price_per_liter',
    'monthly_quota_liters',
    'consumed_liters',
    'contract_start',
    'contract_end',
])]
class ServiceStation extends Model
{
    use HasFactory;

    protected $table = 'service_stations';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'active_agreement' => 'boolean',
            'price_per_liter' => 'decimal:3',
            'monthly_quota_liters' => 'decimal:2',
            'consumed_liters' => 'decimal:2',
            'contract_start' => 'date',
            'contract_end' => 'date',
        ];
    }

    public function fuelOrders(): HasMany
    {
        return $this->hasMany(FuelOrder::class, 'station_id');
    }
}
