<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = ['actor_id', 'entity_type', 'entity_id', 'action', 'old_value', 'new_value'];

    protected function casts(): array { return ['old_value' => 'array', 'new_value' => 'array']; }

    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
