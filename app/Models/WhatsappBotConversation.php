<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappBotConversation extends Model
{
    protected $fillable = ['phone', 'step', 'answers', 'handed_off', 'inquiry_id', 'assigned_to', 'handling_status'];

    protected function casts(): array { return ['answers' => 'array', 'handed_off' => 'boolean']; }

    public function inquiry(): BelongsTo { return $this->belongsTo(Inquiry::class); }
}
