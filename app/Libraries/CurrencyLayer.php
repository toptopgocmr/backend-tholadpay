<?php
/**
 * Created by PhpStorm.
 * User: evari
 * Date: 11/7/2018
 * Time: 10:22 AM
 */

namespace App\Libraries;


use App\Currency;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class CurrencyLayer
{

    protected $base_api = 'https://apilayer.net/api/';

    private $layer_supported_currencies = [
        "AED", "AFN", "ALL", "AMD", "ANG", "AOA", "ARS", "AUD", "AWG", "AZN", "BAM", "BBD", "BDT", "BGN",
        "BHD", "BIF", "BMD", "BND", "BOB", "BRL", "BSD", "BTC", "BTN", "BWP", "BYN", "BYR", "BZD", "CAD",
        "CDF", "CHF", "CLF", "CLP", "CNY", "COP", "CRC", "CUC", "CUP", "CVE", "CZK", "DJF", "DKK", "DOP",
        "DZD", "EEK", "EGP", "ERN", "ETB", "EUR", "FJD", "FKP", "GBP", "GEL", "GGP", "GHS", "GIP", "GMD",
        "GNF", "GTQ", "GYD", "HKD", "HNL", "HRK", "HTG", "HUF", "IDR", "ILS", "IMP", "INR", "IQD", "IRR",
        "ISK", "JEP", "JMD", "JOD", "JPY", "KES", "KGS", "KHR", "KMF", "KPW", "KRW", "KWD", "KYD", "KZT",
        "LAK", "LBP", "LKR", "LRD", "LSL", "LTL", "LVL", "LYD", "MAD", "MDL", "MGA", "MKD", "MMK", "MNT",
        "MOP", "MRO", "MUR", "MVR", "MWK", "MXN", "MYR", "MZN", "NAD", "NGN", "NIO", "NOK", "NPR", "NZD",
        "OMR", "PAB", "PEN", "PGK", "PHP", "PKR", "PLN", "PYG", "QAR", "RON", "RSD", "RUB", "RWF", "SAR",
        "SBD", "SCR", "SDG", "SEK", "SGD", "SHP", "SLL", "SOS", "SRD", "STD", "SVC", "SYP", "SZL", "THB",
        "TJS", "TMT", "TND", "TOP", "TRY", "TTD", "TWD", "TZS", "UAH", "UGX", "USD", "UYU", "UZS", "VEF",
        "VND", "VUV", "WST", "XAF", "XAG", "XAU", "XCD", "XDR", "XOF", "XPF", "YER", "ZAR", "ZMK", "ZMW",
        "ZWL"
    ];

    private $source_currency = "USD";

    public function get_currencies_rate()
    {
        $crs = Currency::pluck('code')->toArray();
        $crs = array_intersect($crs, $this->layer_supported_currencies);
        $crs = implode(",", $crs);
        $params = [
            'access_key' => env('CURRENCY_LAYER_KEY'),
            'currencies' => $crs
        ];
        $client = new Client(["base_uri" => $this->base_api]);
        $response = $client->get('live', ['query' => $params]);
        $body = json_decode($response->getBody(), true);
        var_dump($body);
        $this->update_currencies($body["quotes"]);
//        dd($body);
    }

    private function update_currencies($quotes)
    {
        $l = "currencies rates with ";
        foreach ($quotes as $key => $rate) {
            $l.=$key."=".$rate."; " ;
            $key = explode($this->source_currency, $key, 2);
            Currency::whereCode($key[1])->update(["rate" => $rate]);
        }
        Log::info( $l);
    }
}


