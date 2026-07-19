<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;
use App\Libraries\PeexSms;

class Note extends Model
{
    //
    use RestTrait;

    public static $Verifiables = [PhoneNumber::class, CreditCard::class,
        BankAccount::class,PersonalDocument::class];

    protected $fillable = ['detail', 'user_id', 'verifiable_id', 'verifiable_type', 'status'];

    protected $dates = ['created_at','updated_at'];

    protected $appends=['all_status'];

    public static $Status = ['new', 'done', 'cancel'];

    public function getAllStatusAttribute(){
        return self::$Status;
    }

    public function getLabel(){
        return $this->status ;
    }

    public function setVerifiableTypeAttribute($val)
    {
        if ($val=='phone_number') {
            $this->attributes['verifiable_type']=PhoneNumber::class;
        }elseif($val=='credit_card'){
            $this->attributes['verifiable_type']=CreditCard::class;
        }elseif($val=='bank_account'){
            $this->attributes['verifiable_type']=BankAccount::class;
        }elseif($val=='personal_document'){
            $this->attributes['verifiable_type']=PersonalDocument::class;
        }else{
            $this->attributes['verifiable_type']=$val;
        }
    }

    public function verifiable()
    {
        return $this->morphTo('verifiable');
    }

    public function user(){
        return $this->belongsTo(User::class);
    }




}
