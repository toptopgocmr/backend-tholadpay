<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class Inbound extends Model
{
    //
    use RestTrait;

    protected $fillable = ['remitance_purpose', 'description', 'transaction_id'];

    protected $dates = ['created_at','updated_at'];

    public function transaction(){
        return $this->belongsTo(Transaction::class);
    }

    public static function boot()
    {
        parent::boot();
    }
}
