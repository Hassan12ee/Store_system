<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Favorite;

class Product extends Model
{
    //
        protected $fillable = [
        'name',
        'Photos',
        'quantity',
        'specifications',
        'price',
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


}
