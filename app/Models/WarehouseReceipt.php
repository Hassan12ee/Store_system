<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarehouseReceipt extends Model
{
    protected $fillable = [
        'product_id',
        'quantity_received',
        'received_by_employee_id',
        'received_at',
        'purchase_price',
        'notes',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'received_by_employee_id');
    }
}

