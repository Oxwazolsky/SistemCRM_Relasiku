<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = ['name', 'category', 'active'];

    protected function casts(): array { return ['active' => 'boolean']; }

    public function inquiries(): HasMany { return $this->hasMany(Inquiry::class); }
    public function opportunities(): HasMany { return $this->hasMany(Opportunity::class); }
}
