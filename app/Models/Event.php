<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'date',
        'time',
        'location',
        'event_status',
        'description',
        'banner',
        'ticket_quantity',
        'ticket_price',
        'organizer_id',
    ];

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'categories_events');
    }

    public function wallets(){
        return $this->belongsToMany(Wallet::class, 'event_payment_methods');
    }

    public function participants(){
        return $this->belongsToMany(User::class, 'tickets');
    }

    public function organizer()
    {
        return $this->belongsTo(RegisteredUser::class, 'organizer_id');
    }
}
