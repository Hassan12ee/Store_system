<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

}
