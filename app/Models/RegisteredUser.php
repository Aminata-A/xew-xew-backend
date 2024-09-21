<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RegisteredUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'password',
        'role',
        'balance',
        'photo',
        'status'
    ];

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
