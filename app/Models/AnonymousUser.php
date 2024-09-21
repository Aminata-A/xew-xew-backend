<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AnonymousUser extends Model
{
    use HasFactory;

    public function user()
    {
        return $this->morphOne(User::class, 'userable');
    }
}
