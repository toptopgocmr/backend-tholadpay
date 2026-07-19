<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class Town extends Model
{
    //
    use RestTrait;


    protected $fillable = ['name', 'country_id', 'district'];

    protected $dates = ['created_at','updated_at'];



    public function getLabel()
    {
        return $this->name ;
    }

    public function addresses(){
        return $this->hasMany(Address::class);
    }

    public function country(){
        return $this->belongsTo(Country::class);
    }
}
