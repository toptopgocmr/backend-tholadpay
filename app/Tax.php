<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    //
    use RestTrait;


    protected $fillable = ['code', 'libelle', 'type', 'valeur', 'assiette', 'zone_id', 'statut', 'ordre'];

    protected $dates = ['created_at','updated_at'];



    public function getLabel()
    {
        return $this->libelle ;
    }

    public function zone(){
        return $this->belongsTo(Zone::class);
    }
}
