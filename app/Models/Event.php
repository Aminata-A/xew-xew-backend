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
        'category_id',
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

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
