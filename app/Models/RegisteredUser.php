<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class RegisteredUser extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

        // Add the methods required by the JWTSubject interface
        public function getJWTIdentifier()
        {
            return $this->getKey();
        }

        public function getJWTCustomClaims()
        {
            return [];
        }
    protected $fillable = [
        'password',
        'role',
        'balance',
        'photo',
        'status'
    ];

    // public function event(){
    //     return $this->belongsToMany(Event::class, 'tickets');
    // }
    public function user()
    {
        return $this->morphOne(User::class, 'userable');
    }

    public function wallets(){
        return $this->hasMany(Wallet::class);
    }


       /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
