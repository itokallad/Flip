<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderStatusTransition extends Model
{
    /** @use HasFactory<\Database\Factories\OrderStatusTransitionFactory> */
    use HasFactory;

    protected $fillable = ['order_id', 'status'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
