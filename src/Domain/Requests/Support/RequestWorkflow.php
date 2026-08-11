<?php

namespace Domain\Requests\Support;

use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Models\RequestStatusHistory;

final class RequestWorkflow
{
    public static function record(
        MobilizationRequest $request,
        string $toStatus,
        string $action,
        ?int $userId = null,
        ?string $observation = null,
        ?string $fromStatus = null
    ): void {
        RequestStatusHistory::create([
            'request_id' => $request->id,
            'user_id' => $userId,
            'from_status' => $fromStatus ?? $request->status,
            'to_status' => $toStatus,
            'action' => $action,
            'observation' => $observation,
        ]);
    }
}
