<?php

namespace App\Libraries;

use Twilio;

class PeexSms
{

    public static function send_sms($to, $msg){
        PeexSms::send_sms_twilio($to, $msg);
    }

    public static function send_sms_twilio($to, $msg){
        try{
            Twilio::message($to, $msg);
        }catch (\Exception $e ){
            \Log::warning($e->getMessage(  ));
        }
    }
}