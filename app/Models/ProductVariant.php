<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    //

        protected $fillable = [
            'product_id', 'price', 'quantity', 'sku_En','sku_Ar' , 'photo', 'weight', 'dimensions', 'warehouse_id', 'warehouse_quantity', 'barcode'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'product_variant_attributes')
                    ->withPivot('attribute_value_id')
                    ->withTimestamps();
    }

    public function attributeValues()
    {
        return $this->belongsToMany(AttributeValue::class, 'product_variant_attributes')
                    ->withPivot('attribute_id')
                    ->withTimestamps();
    }
    public function values()
{
    return $this->belongsToMany(AttributeValue::class, 'product_variant_attributes')
                ->withPivot('attribute_id')
                ->with('attribute') // تحميل الـ Attribute مع الـ Value
                ->withTimestamps();
}

}
