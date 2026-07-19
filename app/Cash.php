<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class Cash extends Model
{
    //
    use RestTrait;

    protected $fillable = ['remitance_purpose', 'mobile_phone', 'code', 'description', 'transaction_id'];

    protected $dates = ['created_at','updated_at'];

    public function transaction(){
        return $this->belongsTo(Transaction::class);
    }
}
