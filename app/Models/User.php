<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function contacts(): HasMany { return $this->hasMany(Contact::class, 'owner_id'); }
    public function interactions(): HasMany { return $this->hasMany(Interaction::class); }
    public function opportunities(): HasMany { return $this->hasMany(Opportunity::class, 'owner_id'); }
    public function tasks(): HasMany { return $this->hasMany(Task::class, 'assigned_to'); }
    public function tickets(): HasMany { return $this->hasMany(Ticket::class, 'assigned_to'); }
    public function activityLogs(): HasMany { return $this->hasMany(ActivityLog::class, 'actor_id'); }
}
