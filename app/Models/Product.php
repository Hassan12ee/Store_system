<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Favorite;

class Product extends Model
{
    //
        protected $fillable = [
        'name',
        'barcode',
        'Photos',
        'quantity',
        'specifications',
        'price',
        'warehouse_quantity',
        'size',
        'dimensions',
        'warehouse_id',
        'main_photo',
    ];
protected $casts = [
    'Photos' => 'array',
];
// app/Models/Product.php
public function favoritedBy()
{
    return $this->hasMany(Favorite::class);
}

// app/Models/Product.php

public function receipts()
{
    return $this->hasMany(WarehouseReceipt::class);
}

public function orderDetails()
{
    return $this->hasMany(OrderDetail::class);
}

public function returnedProducts()
{
    return $this->hasMany(ReturnedProduct::class);
}

// الكمية الحالية في المخزون (ديناميك)
public function getStockQuantityAttribute()
{
    $received = $this->receipts()->sum('quantity_received');
    $sold     = $this->orderDetails()->sum('quantity');
    $returned = $this->returnedProducts()->sum('quantity');

    return $received - $sold + $returned;
}

}
