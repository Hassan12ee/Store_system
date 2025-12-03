<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderConfirmAttempt extends Model
{
    //
    use HasFactory;

       protected $fillable = [
        'order_id',
        'attempt_number',
        'status',
        'method',
        'employee_id'
    ];

}
