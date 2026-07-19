<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class Withdraw extends Model
{
    //
    use RestTrait;

    protected $fillable = ['amount', 'mobile_phone', 'code', 'ranking', 'withdraw_status',  'justif_picture', 'status', 'user_id'];

    protected $dates = ['created_at','updated_at'];

    public function user(){
        return $this->belongsTo(User::class);
    }
}
