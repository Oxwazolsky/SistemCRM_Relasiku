<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = ['opportunity_id', 'contact_id', 'order_code', 'final_value', 'status', 'cancelled_at', 'cancellation_reason'];

    protected function casts(): array { return ['final_value' => 'decimal:2', 'cancelled_at' => 'datetime']; }

    public function opportunity(): BelongsTo { return $this->belongsTo(Opportunity::class); }
    public function contact(): BelongsTo { return $this->belongsTo(Contact::class); }
    public function tickets(): HasMany { return $this->hasMany(Ticket::class); }
}
