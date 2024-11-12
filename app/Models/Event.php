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
        'description',
        'location',
        'date',
        'time',
        'banner',
        'organizer_id',
        'ticket_types', // Ajout de ticket_types ici
        'event_status'
    ];

    protected $casts = [
        'ticket_types' => 'json', // Caster ticket_types en JSON
    ];



    public function categories()
    {
        return $this->belongsToMany(Category::class, 'categories_events');
    }

//     public function categories()
// {
//     return $this->hasManyThrough(Category::class, 'categories_events', 'event_id', 'category_id');
// }


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
