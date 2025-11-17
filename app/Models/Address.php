<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\Employee;
use App\Models\Citie;
use App\Models\Governorate;

class Address extends Model
{
    use HasFactory;

    // Explicitly define the primary key and its behavior
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'employee_id',
        'governorate_id',
        'city_id',
        'street',
        'comments',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
    public function city()
    {
        return $this->belongsTo(Citie::class, 'city_id');
    }
    public function governorate()
    {
        return $this->belongsTo(Governorate::class, 'governorate_id');
    }
}
