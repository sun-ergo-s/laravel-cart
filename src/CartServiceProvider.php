<?php

namespace SunErgoS\LaravelCart;

use Illuminate\Support\ServiceProvider;
use SunErgoS\LaravelCart\Cart;

class CartServiceProvider extends ServiceProvider {

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    public function register(): void
    {

        $this->app->singleton('cart', function() {
            return new Cart();
        });

    }
    
}