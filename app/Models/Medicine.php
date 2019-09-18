<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    public function medicineType()
    {
        return $this->belongsTo('App\Models\MedicineType');
    }

    public function company()
    {
        return $this->belongsTo('App\Models\MedicineCompany');
    }
}
