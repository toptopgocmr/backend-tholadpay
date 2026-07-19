<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class Prefunding extends Model
{
    //
    use RestTrait;

    protected $fillable = ['amount','paiement_type', 'date_paiement', 'description', 'valid', 'status', 'prove', 'agent_id'];

    protected $dates = ['created_at','updated_at'];

    public function agent(){
        return $this->belongsTo(Agent::class);
    }
}
