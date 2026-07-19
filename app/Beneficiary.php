<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class Beneficiary extends Model
{
    //
    use RestTrait;

    protected $fillable = ['first_name', 'last_name', 'phone_number',  'phone_number_credit',  'iban',  'bank_name'
        , 'short_code', 'type', 'country', 'sender_id', 'status'
    ];

    protected $dates = ['created_at','updated_at'];

    public function sender(){
        return $this->belongsTo((Sender::exists()) ? Sender::class : null);
    }
}
