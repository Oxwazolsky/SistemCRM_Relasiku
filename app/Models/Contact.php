<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    protected $fillable = ['name', 'phone', 'email', 'organization', 'stage', 'source', 'owner_id'];

    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function inquiries(): HasMany { return $this->hasMany(Inquiry::class); }
    public function interactions(): HasMany { return $this->hasMany(Interaction::class); }
    public function opportunities(): HasMany { return $this->hasMany(Opportunity::class); }
    public function orders(): HasMany { return $this->hasMany(Order::class); }
    public function tickets(): HasMany { return $this->hasMany(Ticket::class); }
}
