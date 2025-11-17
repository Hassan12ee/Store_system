<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Governorate extends Model
{
    //
        protected $fillable = ['governorate_name_en', 'governorate_name_ar	', 'province_id','is_active_shipping'];

    public function cities()
    {
        return $this->hasMany(Citie::class);
    }
        public function provinces()
        {
            return $this->belongsTo(Province::class);

        }
}
