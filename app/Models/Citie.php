<?php

namespace App\Models;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class Citie extends Model
{
    protected $fillable = ['governorate_id', 'city_name_en', 'city_name_ar', 'is_active_shipping'];


    public function governorates()
    {
        return $this->belongsTo(Governorate::class);
    }
}
