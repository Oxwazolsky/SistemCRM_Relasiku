<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Opportunity extends Model
{
    protected $fillable = ['contact_id', 'service_id', 'owner_id', 'title', 'estimated_value', 'quote_summary', 'final_value', 'stage', 'lost_reason', 'closed_at'];

    protected function casts(): array
    {
        return ['estimated_value' => 'decimal:2', 'final_value' => 'decimal:2', 'closed_at' => 'datetime'];
    }

    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function tasks(): HasMany { return $this->hasMany(Task::class); }
    public function order(): HasOne { return $this->hasOne(Order::class); }
}
