<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount',
        'status',
        'order_id',
        'user_id',
    ];

    public function transactionable(){
        return $this->morphTo();
    }

      // Méthode pour changer le statut de la transaction
      public function markAsPaid()
      {
          $this->status = 'success';
          $this->save();
      }

      public function markAsFailed()
      {
          $this->status = 'failed';
          $this->save();
      }
}
