<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RegisteredUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'password',
        'role',
        'balance',
        'photo',
        'status'
    ];

    protected $table = 'users';


    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope('registered', function (Builder $builder) {
            $builder->where('type', 'registered');
        });
    }
    public function user()
    {
        return $this->belongsTo(User::class);
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
