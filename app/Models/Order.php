<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\OrderConfirmAttempt;
use App\Models\OrderConfirmationSession;

class Order extends Model
{
    use HasFactory;

    protected $primaryKey = 'order_id'; // since the primary key is order_id

    protected $fillable = [
        'order_date',
        'customer_id',
        'created_by_employee_id',
        'address_id',
        'total_price'
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'created_by_employee_id');
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function details()
    {
        return $this->hasMany(OrderDetail::class, 'order_id');
    }
    public function Attempts()
    {
        return $this->hasMany(OrderConfirmAttempt::class, 'order_id');
    }
    public function Sessions()
    {
        return $this->hasMany(OrderConfirmationSession::class, 'order_id');
    }
}

