<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Favorite;

class Product extends Model
{
    //
    protected $fillable = [
        'name_Ar', 'name_En','barcode', 'Photos', 'main_photo', 'specifications', 'brand_id', 'category_id'
    ];
protected $casts = [
    'Photos' => 'array',
];
// app/Models/Product.php
// public function favoritedBy()
// {
//     return $this->hasMany(Favorite::class);
// }
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
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
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    // مخزن المنتج
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
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
