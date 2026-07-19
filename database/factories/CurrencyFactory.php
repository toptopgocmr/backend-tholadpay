<?php

use App\Helpers\FactoryHelper;
use Faker\Generator as Faker;

$factory->define(App\Currency::class, function (Faker $faker) {
    $cs = \App\Currency::get();
    if (env("APP_ENV") == "testing"){
        $c = FactoryHelper::getOrCreate(\App\Country::class,true);
    }else{
        if($cs->count()==0){
            $c = \App\Country::where('is_covered',true)->first();
        }else{
            $c = \App\Country::whereNotIn('currency_code', $cs->pluck('code'))
                ->where('is_covered',true)->first();
        }

    }

    return [
        //
        'code' => $c->currency_code,
        'symbol'=>$c->currency_symbol,
        'rate'=> $faker->randomFloat(5,0.0001,2)
    ];
});
