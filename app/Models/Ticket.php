<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    protected $fillable = ['contact_id', 'order_id', 'assigned_to', 'subject', 'description', 'resolution', 'status', 'resolved_at', 'inquiry_id'];

    protected function casts(): array { return ['resolved_at' => 'datetime']; }

    public function inquiry(): BelongsTo { return $this->belongsTo(Inquiry::class); }

    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
}
