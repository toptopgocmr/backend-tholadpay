<?php

namespace App\Providers\Validation;

use Illuminate\Support\ServiceProvider;

class ValidationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
        // Need to override the default validator with our own validator
        // We can do that by using the resolver function
        $this->app->validator->resolver(function ($translator, $data, $rules, $messages) {
            // This class will hold all our custom validations
            return new CustomValidation($translator, $data, $rules, $messages);
        });
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
