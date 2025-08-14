<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VariantBrand extends Model
{
    //
        protected $fillable = [
        'variant_id',
        'brand_id',
        'quantity'
    ];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function brand()
    {
        return $this->belongsTo(AttributeValue::class, 'brand_id');
    }
}
