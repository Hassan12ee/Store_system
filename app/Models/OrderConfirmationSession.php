<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderConfirmationSession extends Model
{
    //
    use HasFactory;

       protected $fillable = [
        'order_id',
        'employee_id',
        'started_at',
        'ended_at',
        'status'
    ];

}
