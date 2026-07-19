<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class Mobile extends Model
{
    //
    use RestTrait;

    protected $fillable = ['mobile_phone_credit', 'mobile_phone_debit', 'outbound_id', 'inbound_id'];

    protected $dates = ['created_at','updated_at'];

    public function outbound(){
        return $this->belongsTo(Outbound::class);
    }

    public function inbound(){
        return $this->belongsTo(Inbound::class);
    }

    public static function boot()
    {
        parent::boot();
    }
}
