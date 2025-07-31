<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnedProduct extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'is_damaged',
        'return_reason',
        'return_status',
        'received_at',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

