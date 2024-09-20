<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'type',
    ];

    public function wallet(){
        return $this->hasOne(Wallet::class);
    }

    public function transactions(){
        return $this->hasMany(Transaction::class);
    }

    public function tickets(){
        return $this->hasMany(Ticket::class);
    }

    public function events(){
        return $this->hasMany(Event::class);
    }

    public function registeredUsers(){
        return $this->hasMany(RegisteredUser::class);
    }

    public function scopeRegistered($query)
    {
        return $query->where('type', 'registered');
    }

    public function scopeAnonymous($query)
    {
        return $query->where('type', 'anonymous');
    }



    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }
}
