<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    //

    use RestTrait;


    protected $fillable = ['name','path','alt','user_id','sender_id','agent_id', 'prefunding_id', 'withdraw_id', 'status'];

    protected $dates = ['created_at','updated_at'];

    public function getLabel()
    {
        return $this->name ;
    }

    public function sender(){
        return $this->belongsTo(Sender::class);
    }

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function agent(){
        return $this->belongsTo(Agent::class);
    }

    public function prefunding(){
        return $this->belongsTo(Prefunding::class);
    }

    public function withdraw(){
        return $this->belongsTo(Withdraw::class);
    }
}
