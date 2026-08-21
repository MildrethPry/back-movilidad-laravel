<?php

namespace Domain\Requests\Models;

use Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'request_id',
    'user_id',
    'attended',
    'invitation_status',
    'reject_reason',
    'responded_at',
])]
class PassengerManifest extends Model
{
    use HasFactory;

    protected $table = 'passenger_manifests';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'attended' => 'boolean',
            'responded_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(MobilizationRequest::class, 'request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
