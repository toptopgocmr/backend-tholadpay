<?php

namespace App\Libraries;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;

class Payci
{

    protected $base_api;
    protected $api_key;

    public function __construct() {
        $this->base_api = env('PAYCI_BASE_URL');
        $this->api_key = env('PAYCI_API_KEY');
    }

    public function authentication(){
        $client = new Client();
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
        $body = json_encode(array('apikey' => $this->api_key));
        $uri = $this->base_api . 'auth/index.php';
        $request = new Request('POST', $uri, $headers, $body);
        $res = $client->sendAsync($request)->wait();
        Log::info($res->getBody());
        return $res;
    }

    public function get_balance($country){
        $this->authentication();
        $client = new Client();
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
        $body = json_encode(array('apikey' => $this->api_key, 'balance' => $country));
        $uri = $this->base_api . 'getbalance/index.php';
        $request = new Request('POST', $uri, $headers, $body);
        $res = $client->sendAsync($request)->wait();
        Log::info($res->getBody());
        return $res;
    }

    public function get_all_balance(){
        $this->authentication();
        $client = new Client();
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
        $body = json_encode(array('apikey' => $this->api_key));
        $uri = $this->base_api . 'getbalance/all/index.php';
        $request = new Request('POST', $uri, $headers, $body);
        $res = $client->sendAsync($request)->wait();
        Log::info('Retour Balance');
        Log::info($res->getBody());
        return $res->getBody();
    }

    public function send_transaction($transaction){
        $this->authentication();
        $client = new Client();
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
        $body = json_encode(array(
            'apikey' => $this->api_key,
            'id_transaction' => $transaction['id_transaction'],
            'beneficiary' => $transaction['beneficiary'],
            'full_name' => $transaction['full_name'],
            'amount' => $transaction['amount'],
            'callback_url' => 'test',
            'method' => "Mobile_money"
        ));
        Log::info($body);
        $uri = $this->base_api . 'send/index.php';
        Log::info('test 1');
        $request = new Request('POST', $uri, $headers, $body);
        $res = $client->sendAsync($request)->wait();
        Log::info($res->getBody());
        return $res->getBody();
    }

    public function get_status($transaction){
        $this->authentication();
        $client = new Client();
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
        $body = json_encode(array('apikey' => $this->api_key, 'id_transaction' => $transaction['id_transaction']));
        $uri = $this->base_api . 'status/index.php';
        $request = new Request('POST', $uri, $headers, $body);
        $res = $client->sendAsync($request)->wait();
        Log::info($res->getBody());
        return $res->getBody();
    }

}