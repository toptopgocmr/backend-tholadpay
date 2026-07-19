<?php

namespace App\Libraries;

//require 'vendor/autoload.php';

use Aws\Sns\SnsClient;
use Aws\Exception\AwsException;

error_reporting(E_ALL);
ini_set("display_errors", 1);


class AwsSms
{

    public static function send_sms($to, $msg){
        $client = new SnsClient([
            'credentials' => array(
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ),
            'region' => env('AWS_DEFAULT_REGION'),
            'version' => '2010-03-31'
        ]);

        $message = $msg;
        $phone = $to;
        $phone2 = $to;
        if (!str_starts_with($phone, '242')){
            $phone2 = '242' . ltrim($phone);
        }
        $phone2 = '+' . ltrim($phone2);

        try {
            $result = $client->publish([
                'Message' => $message,
                'PhoneNumber' => $phone2,
            ]);
            var_dump($result);
            \Log::debug($result);
            return $result;
        } catch (AwsException $e) {
            // output error message if fails
            error_log($e->getMessage());
            \Log::warning($e->getMessage());
        }
    }
}