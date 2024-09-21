<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventPaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'payment_method_id'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}
