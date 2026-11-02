<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HandlingNote extends Model
{
    protected $fillable = ['subject_type', 'subject_id', 'user_id', 'assigned_to', 'submission_id', 'action', 'body'];
    public function user() { return $this->belongsTo(User::class); }
}
