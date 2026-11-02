<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inquiry extends Model
{
    protected $fillable = ['contact_id', 'service_id', 'event_date', 'requirement', 'consent_at', 'source', 'submitted_name', 'submitted_phone', 'submitted_email', 'status', 'assigned_to', 'verified_at', 'intent', 'opportunity_id', 'handling_status'];

    protected function casts(): array { return ['event_date' => 'date', 'consent_at' => 'datetime', 'verified_at' => 'datetime']; }

    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_to'); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }

}
