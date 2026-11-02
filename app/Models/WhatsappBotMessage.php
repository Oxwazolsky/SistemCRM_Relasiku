<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappBotMessage extends Model
{
    protected $fillable = ['message_id', 'conversation_id', 'reply', 'sender', 'delivery_status', 'outbound_message_id', 'delivery_error', 'sent_at', 'media_id'];

    public function conversation(): BelongsTo { return $this->belongsTo(WhatsappBotConversation::class, 'conversation_id'); }
}
