<?php

use App\Helpers\FactoryHelper;
use Faker\Generator as Faker;

$factory->define(App\Country::class, function (Faker $faker) {

    $n = $faker->country;

    $i2 = $faker->randomLetter.$faker->randomLetter;
    $i3 = $faker->randomLetter.$faker->randomLetter.$faker->randomLetter;
    return [
        //
//        'id'=>$faker->randomNumber(3),
        'capital'=>$faker->city,
        'citizenship'=>$n.'ians',
        'country_code'=>$faker->countryCode,
        'currency'=>$faker->currencyCode,
        'currency_code'=>$faker->currencyCode,
        'currency_sub_unit'=>1,
        'currency_symbol'=>$faker->randomLetter,
        'currency_decimals'=>0,
        'full_name'=>$n,
        'iso_3166_2'=>$i2,
        'iso_3166_3'=>$i3,
        'name'=>$n,
        'region_code'=>$faker->countryCode,
        'sub_region_code'=>$faker->countryCode,
        'eea'=>1,
        'calling_code'=>$faker->countryCode,
        'is_covered'=>false,
        'is_activated'=>false,
        'flag'=>'CM.png'
    ];
});
