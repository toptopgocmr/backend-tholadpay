<?php

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| Here you may define all of your model factories. Model factories give
| you a convenient way to create models for testing and seeding your
| database. Just tell the factory how a default model should look.
|
*/

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Helpers\FactoryHelper;
use Illuminate\Support\Str;

$factory->define(App\User::class, function (Faker\Generator $faker) {
    static $password;
    $k1 = $faker->boolean(45);
    $d = null;
    $s =array_rand(\App\User::$Status);

    return [
        'email' => $faker->unique()->safeEmail,
        'phone_number'=>$faker->phoneNumber,
        'failed_password_attemps'=>$faker->numberBetween(0,3),
        'is_active'=>$faker->boolean(80),
        'status'=>$s,
        'picture'=>'',
        'first_name'=>$faker->firstName,
        'last_name'=>$faker->lastName,
        'password' => $password ?: $password = bcrypt('password'),
        'last_login'=>$faker->dateTime(),
        'remember_token' => Str::random(10),
        // 'remember_token' => str_random(10),
    ];
});
