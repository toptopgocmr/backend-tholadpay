<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;
use App\Libraries\PeexSms;

class Verification extends Model
{
    //
    use RestTrait;

    public static $Verifiables = [PhoneNumber::class, CreditCard::class,
        BankAccount::class,PersonalDocument::class];

    protected $fillable = ['code','type','status','verifiable_type','verifiable_id'];

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

    public function author(){
        return $this->belongsTo(User::class);
    }

    public function send_code($phone){
        if ($this->verifiable_type == PhoneNumber::class){
            $text = '<#> ' . $this->code . ' is Your PEEX verification code. ';
            $text .= "TOkGdsadMLP";
            PeexSms::send_sms($phone, $text);
        }
    }

    public static function generate_secure_code($type){
        $verification = new Verification();
        $verification->code = Verification::generatePIN();
        $verification->setVerifiableTypeAttribute($type);
        $verification->status = 'new';

        return $verification;
    }

    public static function generatePIN($digits = 6){
        $i = 0;
        $pin = "";
        while($i < $digits){
            $pin .= mt_rand(0, 9);
            $i++;
        }
        return $pin;
    }

}