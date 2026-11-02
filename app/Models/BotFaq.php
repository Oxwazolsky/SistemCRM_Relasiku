<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotFaq extends Model
{
    protected $fillable = ['question', 'answer', 'active'];
    protected function casts(): array { return ['active' => 'boolean']; }
}
