<?php

use App\Helpers\FactoryHelper;
use Faker\Generator as Faker;

$factory->define(App\Address::class, function (Faker $faker) {

    $t = (FactoryHelper::getOrCreate(\App\Town::class))->id;
    $u = (FactoryHelper::getOrCreate(\App\User::class))->id;

    return [
        //
        'name'=>$faker->address,
        'is_primary'=>false,
        'user_id'=>$u,
        'town_id'=>$t
    ];
});
