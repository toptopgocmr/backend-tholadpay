<?php

namespace App\Providers;

use App\Community;
use App\CommunityUser;
use App\Country;
use App\Currency;
use App\Invitation;
use App\MessageFile;
use App\PhoneNumber;
use App\Subscription;
use App\SubscriptionUser;
use Auth;
use Illuminate\Support\ServiceProvider;

// use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Schema::defaultStringLength(255);
        Country::updated(function(Country $c){
            if($c->is_covered&&!Currency::whereCode($c->currency_code)->exists()){
                Currency::create([
                    'code' => $c->currency_code,
                    'symbol'=>$c->currency_symbol,
                    'rate'=>1,
                ]);
            }
        });
        Country::created(function(Country $c){
            if($c->is_covered&&!Currency::whereCode($c->currency_code)->exists()){
                Currency::create([
                    'code' => $c->currency_code,
                    'symbol'=>$c->currency_symbol,
                    'rate'=>1,
                ]);
            }
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
