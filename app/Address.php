<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    //
    use RestTrait;


    protected $fillable = ['name', 'description', 'is_primary', 'town_id', 'user_id', 'province'];

    protected $dates = ['created_at','updated_at'];



    public function getLabel()
    {
        return $this->name ;
    }

    public function users(){
        return $this->hasMany(User::class);
    }

    public function town(){
        return $this->belongsTo((Town::exists()) ? Town::class : null);
    }

    public function user(){
        return $this->belongsTo((User::exists()) ? User::class : null);
    }

}
