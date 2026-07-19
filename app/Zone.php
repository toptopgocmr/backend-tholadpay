<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    //
    use RestTrait;


    protected $fillable = ['name', 'description', 'limit_transac_day', 'status'];

    protected $dates = ['created_at','updated_at'];



    public function getLabel()
    {
        return $this->name ;
    }

    public function tarifications(){
        return $this->hasMany(Address::class);
    }

    public function countries(){
        return $this->hasMany(Country::class);
    }
}
