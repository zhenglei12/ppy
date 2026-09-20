<?php

namespace App\Providers;

use App\Http\Exceptions\ExceptionFactory;
use Illuminate\Support\ServiceProvider;

class ExceptionFactoryFacadeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton("ExceptionFactory", function ($app) {
            return new ExceptionFactory();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
