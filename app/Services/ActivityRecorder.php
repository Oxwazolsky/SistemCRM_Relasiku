<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityRecorder
{
    public function record(Model $model, string $action, array $before = [], array $after = []): void
    {
        ActivityLog::create([
            'actor_id' => Auth::id(), 'entity_type' => $model->getMorphClass(),
            'entity_id' => $model->getKey(), 'action' => $action,
            'old_value' => $before, 'new_value' => $after,
        ]);
    }

    public function changes(Model $model, array $before): void
    {
        $after = $model->only(array_keys($before));
        if ($before !== $after) $this->record($model, 'updated', $before, $after);
    }
}
