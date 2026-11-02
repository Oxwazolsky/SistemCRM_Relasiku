<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    protected $fillable = ['opportunity_id', 'assigned_to', 'due_at', 'last_reminded_at', 'status', 'note', 'cancelled_at', 'cancellation_reason'];

    protected function casts(): array { return ['due_at' => 'datetime', 'last_reminded_at' => 'datetime', 'cancelled_at' => 'datetime']; }

    public function opportunity(): BelongsTo { return $this->belongsTo(Opportunity::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
}
