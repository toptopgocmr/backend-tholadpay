<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class Tarification extends Model
{
    //
    use RestTrait;


    protected $fillable = ['tarif_1', 'tarif_2', 'frais', 'status', 'zone_id'];

    protected $dates = ['created_at','updated_at'];



    public function getLabel()
    {
        return $this->frais ;
    }

    public function zone(){
        return $this->belongsTo(Zone::class);
    }

    public function transactions(){
        return $this->hasMany(Transaction::class);
    }
}
