<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Interaction extends Model
{
    protected $fillable = ['contact_id', 'user_id', 'channel', 'occurred_at', 'summary', 'next_step'];

    protected function casts(): array { return ['occurred_at' => 'datetime']; }

    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
